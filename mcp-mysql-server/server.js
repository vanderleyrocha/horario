import mysql from "mysql2/promise";
import { z } from "zod";
import { McpServer } from "@modelcontextprotocol/sdk/server/mcp.js";
import { StdioServerTransport } from "@modelcontextprotocol/sdk/server/stdio.js";

const CONFIG = {
    host: process.env.MCP_MYSQL_HOST || "127.0.0.1",
    port: Number(process.env.MCP_MYSQL_PORT || 3306),
    user: process.env.MCP_MYSQL_USER || "root",
    password: process.env.MCP_MYSQL_PASSWORD || "",
    database: process.env.MCP_MYSQL_DATABASE || "u815349007_horario",
    connectionLimit: Number(process.env.MCP_MYSQL_CONNECTION_LIMIT || 5),
    maxRows: Number(process.env.MCP_MYSQL_MAX_ROWS || 200),
    queryTimeoutMs: Number(process.env.MCP_MYSQL_QUERY_TIMEOUT_MS || 15000),
};

if (!CONFIG.database) {
    console.error("MCP_MYSQL_DATABASE não informado.");
    process.exit(1);
}

const pool = mysql.createPool({
    host: CONFIG.host,
    port: CONFIG.port,
    user: CONFIG.user,
    password: CONFIG.password,
    database: CONFIG.database,
    waitForConnections: true,
    connectionLimit: CONFIG.connectionLimit,
    queueLimit: 0,
    multipleStatements: false,
});

function okText(text) {
    return {
        content: [{ type: "text", text }],
    };
}

function errorText(message) {
    return {
        content: [{ type: "text", text: `Erro: ${message}` }],
        isError: true,
    };
}

function sanitizeIdentifier(name) {
    if (!/^[A-Za-z0-9_]+$/.test(name)) {
        throw new Error("Identificador inválido. Use apenas letras, números e underscore.");
    }
    return name;
}

function stripTrailingSemicolon(sql) {
    return sql.trim().replace(/;+$/g, "").trim();
}

function validateReadOnlySql(sql) {
    const normalized = stripTrailingSemicolon(sql);

    if (!normalized) {
        throw new Error("SQL vazio.");
    }

    if (normalized.includes(";")) {
        throw new Error("Múltiplas instruções não são permitidas.");
    }

    if (/--|\/\*|\*\//.test(normalized)) {
        throw new Error("Comentários SQL não são permitidos.");
    }

    const allowed = /^(SELECT|SHOW|DESCRIBE|DESC|EXPLAIN)\b/i.test(normalized);
    if (!allowed) {
        throw new Error("Apenas SELECT, SHOW, DESCRIBE/DESC e EXPLAIN são permitidos.");
    }

    const forbidden =
        /\b(INSERT|UPDATE|DELETE|REPLACE|UPSERT|ALTER|DROP|TRUNCATE|CREATE|RENAME|GRANT|REVOKE|CALL|DO|HANDLER|LOAD|SET|LOCK|UNLOCK)\b/i;

    if (forbidden.test(normalized)) {
        throw new Error("Comando bloqueado por segurança.");
    }

    return normalized;
}

function enforceLimit(sql, maxRows) {
    const normalized = stripTrailingSemicolon(sql);

    if (!/^SELECT\b/i.test(normalized)) {
        return normalized;
    }

    if (/\bLIMIT\s+\d+(\s*,\s*\d+)?\b/i.test(normalized)) {
        return normalized;
    }

    return `${normalized} LIMIT ${maxRows}`;
}

async function query(sql, params = []) {
    const connection = await pool.getConnection();
    try {
        await connection.query(`SET SESSION MAX_EXECUTION_TIME = ${CONFIG.queryTimeoutMs}`);
        const [rows, fields] = await connection.query({
            sql,
            values: params,
            timeout: CONFIG.queryTimeoutMs,
        });
        return { rows, fields };
    } finally {
        connection.release();
    }
}

function formatRows(rows) {
    if (!Array.isArray(rows)) {
        return JSON.stringify(rows, null, 2);
    }

    const limited = rows.slice(0, CONFIG.maxRows);
    return JSON.stringify(limited, null, 2);
}

const server = new McpServer({
    name: "mysql-laravel-ga",
    version: "1.0.0",
});

server.tool(
    "list_tables",
    "Lista as tabelas do banco MySQL atual, com nome e tipo.",
    {},
    async () => {
        try {
            const sql = `
        SELECT
          TABLE_NAME AS table_name,
          TABLE_TYPE AS table_type,
          ENGINE AS engine,
          TABLE_ROWS AS estimated_rows
        FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = ?
        ORDER BY TABLE_NAME
      `;

            const { rows } = await query(sql, [CONFIG.database]);

            return okText(formatRows(rows));
        } catch (err) {
            console.error("list_tables:", err);
            return errorText(err.message);
        }
    }
);

server.tool(
    "describe_schema",
    "Descreve o schema de uma tabela: colunas, tipos, nulidade, chave, default e índices.",
    {
        table: z.string().min(1),
    },
    async ({ table }) => {
        try {
            const safeTable = sanitizeIdentifier(table);

            const columnsSql = `
        SELECT
          COLUMN_NAME AS column_name,
          COLUMN_TYPE AS column_type,
          DATA_TYPE AS data_type,
          IS_NULLABLE AS is_nullable,
          COLUMN_KEY AS column_key,
          COLUMN_DEFAULT AS column_default,
          EXTRA AS extra,
          COLUMN_COMMENT AS column_comment,
          ORDINAL_POSITION AS ordinal_position
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
        ORDER BY ORDINAL_POSITION
      `;

            const indexesSql = `
        SELECT
          INDEX_NAME AS index_name,
          COLUMN_NAME AS column_name,
          NON_UNIQUE AS non_unique,
          SEQ_IN_INDEX AS seq_in_index,
          INDEX_TYPE AS index_type
        FROM information_schema.STATISTICS
        WHERE TABLE_SCHEMA = ?
          AND TABLE_NAME = ?
        ORDER BY INDEX_NAME, SEQ_IN_INDEX
      `;

            const [columnsResult, indexesResult] = await Promise.all([
                query(columnsSql, [CONFIG.database, safeTable]),
                query(indexesSql, [CONFIG.database, safeTable]),
            ]);

            const payload = {
                database: CONFIG.database,
                table: safeTable,
                columns: columnsResult.rows,
                indexes: indexesResult.rows,
            };

            return okText(JSON.stringify(payload, null, 2));
        } catch (err) {
            console.error("describe_schema:", err);
            return errorText(err.message);
        }
    }
);

server.tool(
    "run_sql",
    "Executa SQL somente leitura com limites de segurança. Permitido: SELECT, SHOW, DESCRIBE/DESC, EXPLAIN.",
    {
        sql: z.string().min(1),
    },
    async ({ sql }) => {
        try {
            let safeSql = validateReadOnlySql(sql);
            safeSql = enforceLimit(safeSql, CONFIG.maxRows);

            const { rows, fields } = await query(safeSql);

            const payload = {
                executed_sql: safeSql,
                row_count: Array.isArray(rows) ? rows.length : 0,
                fields: Array.isArray(fields)
                    ? fields.map((f) => ({
                        name: f.name,
                        columnType: f.columnType,
                        columnLength: f.columnLength,
                    }))
                    : [],
                rows,
            };

            return okText(JSON.stringify(payload, null, 2));
        } catch (err) {
            console.error("run_sql:", err);
            return errorText(err.message);
        }
    }
);

async function main() {
    const transport = new StdioServerTransport();
    await server.connect(transport);
    console.error("MySQL MCP server conectado via stdio.");
}

main().catch((err) => {
    console.error("Falha ao iniciar MCP server:", err);
    process.exit(1);
});

process.stdin.on("close", async () => {
    console.error("STDIN encerrado. Finalizando MCP server.");
    try {
        await pool.end();
    } catch (err) {
        console.error("Erro ao encerrar pool:", err);
    }
    process.exit(0);
});
