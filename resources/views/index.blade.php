@extends('layouts.app')

@section('content')
<div class="container mx-auto py-10">
    <h1 class="text-3xl font-extrabold mb-6 text-gray-800">Item Prices</h1>

    @php
    // --- Compute average Nature Rune price ---
    $nHigh = data_get($naturePrice, 'high');
    $nLow = data_get($naturePrice, 'low');
    $natureAvg = is_numeric($nHigh) && is_numeric($nLow) ? ($nHigh + $nLow) / 2
    : (is_numeric($nHigh) ? $nHigh
    : (is_numeric($nLow) ? $nLow : 0));

    // --- Normalize items (prices now nested under 'price') ---
    $itemsCollection = collect($items ?? [])->map(function ($item) use ($natureAvg) {
    $high = data_get($item, 'price.high', data_get($item, 'high'));
    $low = data_get($item, 'price.low', data_get($item, 'low'));

    $avgItem = is_numeric($high) && is_numeric($low) ? ($high + $low) / 2
    : (is_numeric($high) ? $high
    : (is_numeric($low) ? $low : 0));

    $ha = data_get($item, 'ha', data_get($item, 'high_alch', 0));
    $profit = $ha - ($avgItem + $natureAvg);

    return array_merge(
    is_array($item) ? $item : (array) $item,
    [
    '_avg_price' => $avgItem,
    '_profit' => $profit,
    ]
    );
    })->sortByDesc('_profit');
    @endphp

    {{-- Nature Rune summary --}}
    <div class="mb-6 p-4 bg-blue-100 border border-blue-300 rounded-lg shadow-sm">
        <p class="text-blue-900 text-lg">
            <strong>Average Nature Rune Price:</strong>
            <span id="naturePrice">{{ number_format((int) round($natureAvg)) }}</span> gp
        </p>
    </div>

    @if ($itemsCollection->isEmpty())
    <div class="p-4 bg-yellow-100 border border-yellow-300 rounded-lg text-yellow-900">
        No items found.
    </div>
    @else
    <!-- Toolbar: Search + Refresh -->
    <div class="mb-4 flex flex-col md:flex-row md:items-center gap-2 md:gap-4">
        <input type="text" id="searchBox"
            class="w-full md:w-72 px-4 py-2 border border-gray-300 rounded-lg focus:ring focus:ring-blue-200"
            placeholder="Search items..." />

        <a href="?refresh" class="text-white bg-blue-700 hover:bg-blue-800 focus:ring-4 focus:ring-blue-300 font-medium rounded-lg text-sm px-5 py-2.5 dark:bg-blue-600 dark:hover:bg-blue-700 focus:outline-none dark:focus:ring-blue-800">Refresh</a>
    </div>

    <!-- Table -->
    <div class="bg-white shadow-lg rounded-lg overflow-hidden">
        <div class="overflow-x-auto">
            <table id="itemsTable" class="min-w-full table-auto">
                <thead class="bg-gray-200 text-gray-800 sticky top-0">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold cursor-pointer" data-sort="string">Item</th>
                        <th class="px-4 py-3 text-right font-semibold cursor-pointer" data-sort="number">Avg Item Price</th>
                        <th class="px-4 py-3 text-right font-semibold cursor-pointer" data-sort="number">High Alch Value</th>
                        <th class="px-4 py-3 text-right font-semibold cursor-pointer" data-sort="number">Nature Rune</th>
                        <th class="px-4 py-3 text-right font-semibold cursor-pointer" data-sort="number">Profit</th>
                        <th class="px-4 py-3 text-right font-semibold cursor-pointer" data-sort="number">GE Limit</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach ($itemsCollection as $item)
                    @php
                    $avgItem = data_get($item, '_avg_price', 0);
                    $ha = data_get($item, 'ha', data_get($item, 'high_alch', 0));
                    $profit = data_get($item, '_profit', 0);
                    $geLimit = data_get($item, 'ge_limit', 'Unknown');
                    @endphp
                    <tr class="hover:bg-gray-50">
                        <td class="px-4 py-3 font-medium text-gray-800">{{ data_get($item, 'name', 'Unknown') }}</td>
                        <td class="px-4 py-3 text-right text-gray-700">
                            <input type="number"
                                class="item-price w-24 text-right border rounded px-1 py-0.5"
                                value="{{ round($avgItem) }}"
                                data-ha="{{ $ha }}" />
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700 ha-value">{{ number_format((int) $ha) }}</td>
                        <td class="px-4 py-3 text-right text-gray-700 nature-value">{{ number_format((int) round($natureAvg)) }}</td>
                        <td class="px-4 py-3 text-right font-semibold profit 
                                           @if($profit > 0) text-green-600
                                           @elseif($profit < 0) text-red-600
                                           @else text-gray-600 @endif">
                            {{ number_format((int) round($profit)) }}
                        </td>
                        <td class="px-4 py-3 text-right text-gray-700">{{ $geLimit }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>

<!-- Dynamic Price & Profit Logic -->
<script>
    const naturePrice = parseFloat(document.getElementById('naturePrice').innerText.replace(/,/g, ''));

    document.querySelectorAll('.item-price').forEach(input => {
        input.addEventListener('input', () => {
            const row = input.closest('tr');
            const haValue = parseFloat(row.querySelector('.ha-value').innerText.replace(/,/g, ''));
            const profitCell = row.querySelector('.profit');

            let itemPrice = parseFloat(input.value) || 0;
            let profit = haValue - (itemPrice + naturePrice);

            // Update Profit Cell
            profitCell.innerText = profit.toLocaleString();

            profitCell.classList.remove('text-green-600', 'text-red-600', 'text-gray-600');
            if (profit > 0) {
                profitCell.classList.add('text-green-600');
            } else if (profit < 0) {
                profitCell.classList.add('text-red-600');
            } else {
                profitCell.classList.add('text-gray-600');
            }
        });
    });

    // --- Search Filter ---
    const searchBox = document.getElementById('searchBox');
    searchBox.addEventListener('keyup', () => {
        const term = searchBox.value.toLowerCase();
        document.querySelectorAll('#itemsTable tbody tr').forEach(row => {
            const itemName = row.querySelector('td').innerText.toLowerCase();
            row.style.display = itemName.includes(term) ? '' : 'none';
        });
    });

    const table = document.getElementById('itemsTable');
    const tbody = table.querySelector('tbody');
    const headers = table.querySelectorAll('thead th');
    // --- Column Sorting ---
    headers.forEach((header, index) => {
        header.addEventListener('click', () => {
            const type = header.dataset.sort;
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const dir = header.dataset.dir === 'asc' ? 'desc' : 'asc';
            header.dataset.dir = dir;

            rows.sort((a, b) => {
                const valA = a.cells[index].innerText.replace(/[^0-9.-]/g, '');
                const valB = b.cells[index].innerText.replace(/[^0-9.-]/g, '');

                if (type === 'number') {
                    return dir === 'asc' ? valA - valB : valB - valA;
                } else {
                    return dir === 'asc' ?
                        a.cells[index].innerText.localeCompare(b.cells[index].innerText) :
                        b.cells[index].innerText.localeCompare(a.cells[index].innerText);
                }
            });

            rows.forEach(row => tbody.appendChild(row));
        });
    });
</script>
@endsection