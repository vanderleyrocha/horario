# Contexto do Projeto: Sistema Gerador de Horários Escolares com Algoritmo Genético

## 1. Visão Geral
Este projeto visa desenvolver um sistema eficiente para a geração automática de horários escolares, resolvendo conflitos de alocação através de Algoritmos Genéticos (AG).
A arquitetura é baseada em microsserviços para garantir escalabilidade e separação de responsabilidades:
- **Motor de Otimização (Backend AI):** Desenvolvido em Python.
- **Painel de Gestão e Interface (Frontend/Admin):** Desenvolvido em PHP usando Laravel, Livewire, Tailwind CSS e MySQL.
- **Ambiente de Desenvolvimento:** VS Code rodando no Windows 11.

**Diretriz para o Copilot:** Ao gerar código, sempre considere este contexto arquitetônico. Respostas para o motor de otimização devem ser estritamente em Python de alta performance. Respostas para a interface devem utilizar as melhores práticas do Laravel e componentes Livewire.

---

## 2. Stack Tecnológico e Bibliotecas
Ao sugerir implementações ou resolver bugs, utilize preferencialmente as seguintes ferramentas:

### Motor Python (API de Processamento)
*   **Framework API:** FastAPI (assíncrono, tipagem estática com Pydantic).
*   **Algoritmo Genético:** DEAP (Distributed Evolutionary Algorithms in Python).
*   **Processamento de Dados e Vetorização:** NumPy e Pandas.
*   **Servidor:** Uvicorn.

### Sistema de Gestão (Interface)
*   **Framework PHP:** Laravel (versão mais recente).
*   **Frontend Reativo:** Livewire + Alpine.js.
*   **Estilização:** Tailwind CSS.
*   **Banco de Dados:** MySQL.

---

## 3. Modelagem do Algoritmo Genético (Regras de Negócio)

### Representação do Cromossomo (O Horário)
*   A grade horária deve ser representada como um array unidimensional (lista plana) para otimizar o processamento.
*   **Gene:** Cada posição na lista representa uma alocação. Estrutura lógica: `[Turma_ID, Professor_ID, Disciplina_ID, Dia_ID, Horario_ID]`.

### Função de Aptidão (Fitness Function)
A função de fitness deve ser altamente otimizada utilizando operações vetorizadas do NumPy para evitar loops aninhados.

**Restrições Fortes (Hard Constraints - Peso Alto/Eliminatório):**
1. O mesmo professor não pode dar aula para duas turmas no mesmo dia e horário.
2. Uma turma não pode ter duas aulas diferentes no mesmo dia e horário.
3. A carga horária máxima semanal do professor não pode ser excedida.
4. Professores só podem ser alocados em seus dias/horários de disponibilidade.

**Restrições Fracas (Soft Constraints - Peso Baixo/Otimização):**
1. Minimizar "janelas" (buracos) no horário dos professores.
2. Agrupar aulas da mesma disciplina no mesmo dia (ex: aulas duplas), caso a regra da escola permita.
3. Distribuir as aulas de uma mesma disciplina uniformemente ao longo da semana.

---

## 4. Roteiro de Implementação Passo a Passo

**Diretriz para o Copilot:** O desenvolvimento deve seguir a ordem abaixo. Ao ser solicitado para iniciar, foque no passo atual antes de pular para o próximo.

### Fase 1: Setup do Motor Python (FastAPI)
1. Criar a estrutura básica do FastAPI.
2. Definir os modelos de entrada e saída (Schemas) usando Pydantic. O sistema deve receber um JSON contendo: `professores` (com disponibilidade e limites), `turmas`, `disciplinas` e `grade_base` (dias e slots de tempo).
3. Criar uma rota POST `/generate-timetable` que receberá o payload JSON.

### Fase 2: Implementação do DEAP (Algoritmo Genético)
1. Configurar os tipos no DEAP: `FitnessMax` e o `Individual` (lista).
2. Criar a função de inicialização populacional que gere horários aleatórios válidos (respeitando a carga horária base).
3. Implementar a função de avaliação (Fitness) vetorizada com NumPy.
4. Definir os operadores genéticos: `cxTwoPoint` (Crossover) e `mutShuffleIndexes` (Mutação customizada para trocar aulas de lugar sem alterar a carga horária total).
5. Integrar o loop evolutivo (`eaSimple` ou customizado) à rota do FastAPI.

### Fase 3: Integração com Laravel e Livewire
1. No Laravel, criar os Models e Migrations para: `Teachers`, `Classes`, `Subjects`, `Availabilities` e `Timetables`.
2. Criar um Service (`TimetableGeneratorService`) responsável por buscar os dados do banco, formatar em JSON e fazer uma requisição HTTP (usando `Http::post`) para a API Python rodando localmente (ex: `http://localhost:8000/generate-timetable`).
3. Criar um componente Livewire (`CreateTimetable`) com um botão para disparar o processo, exibindo um estado de "loading" enquanto o Python processa.
4. Receber a resposta da API Python, salvar as alocações no MySQL e renderizar a grade horária usando uma tabela estilizada com Tailwind CSS.

---

## 5. Padrões de Código e Boas Práticas
*   **Python:** Utilize Type Hints em todas as funções. Mantenha as lógicas de avaliação de fitness isoladas em funções puras para facilitar testes unitários.
*   **Laravel:** Utilize Repository Pattern ou Actions para manter os controllers limpos. Utilize Eloquent Relationships rigorosamente.
*   **Tratamento de Erros:** A API Python deve retornar códigos de erro HTTP claros (422 para dados inválidos, 500 para falhas no algoritmo) caso não consiga encontrar uma solução viável dentro do limite de gerações. O Laravel deve tratar esses erros e exibir mensagens amigáveis no frontend via Livewire.
