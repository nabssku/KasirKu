<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pengeluaran</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
        .text-right { text-align: right; }
        .footer { margin-top: 30px; text-align: right; font-weight: bold; font-size: 14px; }
        .header { text-align: center; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN PENGELUARAN</h2>
        <p>Periode: {{ $start_date }} s/d {{ $end_date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Kategori</th>
                <th>Sumber</th>
                <th>Info Shift</th>
                <th>Keterangan</th>
                <th class="text-right">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td>{{ \Carbon\Carbon::parse($item['date'])->format('d-m-Y H:i') }}</td>
                <td>{{ $item['category_name'] }}</td>
                <td>{{ $item['source'] }}</td>
                <td>
                    @if($item['shift_opened_at'])
                        Buka: {{ \Carbon\Carbon::parse($item['shift_opened_at'])->format('d-m-Y H:i') }}
                    @else
                        -
                    @endif
                </td>
                <td>{{ $item['notes'] }}</td>
                <td class="text-right">Rp {{ number_format($item['total_amount'], 0, ',', '.') }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        TOTAL PENGELUARAN: Rp {{ number_format($total_overall, 0, ',', '.') }}
    </div>
</body>
</html>
