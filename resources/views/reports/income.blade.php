<!DOCTYPE html>
<html>
<head>
    <title>Laporan Pendapatan</title>
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
        <h2>LAPORAN PENDAPATAN</h2>
        <p>Periode: {{ $start_date }} s/d {{ $end_date }}</p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Total Pendapatan</th>
                <th>Keterangan</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data as $item)
            <tr>
                <td>{{ $item['date'] }}</td>
                <td class="text-right">Rp {{ number_format($item['total_revenue'], 0, ',', '.') }}</td>
                <td>{{ $item['description'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        TOTAL PENDAPATAN: Rp {{ number_format($total_overall, 0, ',', '.') }}
    </div>
</body>
</html>
