<div>

    <div class="mb-6">

        <h2 class="text-2xl font-bold text-gray-900">
            Solver Execution #{{ $execution->id }}
        </h2>

        <div class="grid grid-cols-4 gap-4 mt-4">

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Status</p>
                <p class="text-lg font-bold">{{ $executionInfo['status'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Population</p>
                <p class="text-lg font-bold">{{ $executionInfo['population'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Islands</p>
                <p class="text-lg font-bold">{{ $executionInfo['islands'] }}</p>
            </div>

            <div class="bg-white shadow rounded p-4">
                <p class="text-sm text-gray-500">Best Fitness</p>
                <p class="text-lg font-bold">{{ number_format($executionInfo['bestFitness'], 4) }}</p>
            </div>

        </div>
    </div>


    <div class="grid grid-cols-2 gap-6">

        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold mb-2">Fitness Curve</h3>
            <canvas id="fitnessChart"></canvas>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold mb-2">Diversity Curve</h3>
            <canvas id="diversityChart"></canvas>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold mb-2">Entropy Curve</h3>
            <canvas id="entropyChart"></canvas>
        </div>

        <div class="bg-white shadow rounded p-4">
            <h3 class="font-semibold mb-2">Mutation Rate</h3>
            <canvas id="mutationChart"></canvas>
        </div>

        <div class="bg-white shadow rounded p-4 col-span-2">
            <h3 class="font-semibold mb-2">Operator Usage</h3>
            <canvas id="operatorChart"></canvas>
        </div>

        <div class="bg-white shadow rounded p-4 col-span-2">
            <h3 class="font-semibold mb-2">Landscape State</h3>
            <canvas id="landscapeChart"></canvas>
        </div>

    </div>

    <script>
        window.solverMetrics = @json($metrics);
    </script>

    @vite('resources/js/solver-dashboard.js')

</div>
