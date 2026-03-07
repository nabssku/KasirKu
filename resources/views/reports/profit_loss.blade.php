<!DOCTYPE html>
<html>
<head>
    <title>Laporan Laba Rugi</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #ddd; padding: 12px; text-align: left; }
        .text-right { text-align: right; }
        .header { text-align: center; }
        .profit { color: green; font-weight: bold; }
        .loss { color: red; font-weight: bold; }
        .summary { margin-top: 30px; border-top: 2px solid #333; padding-top: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>LAPORAN LABA RUGI</h2>
        <p>Periode: {{ $start_date }} s/d {{ $end_date }}</p>
    </div>

    <table>
        <tbody>
            <tr>
                <td>Total Pendapatan</td>
                <td class="text-right">Rp {{ number_format($total_revenue, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td>Total Pengeluaran</td>
                <td class="text-right">Rp {{ number_format($total_expenses, 0, ',', '.') }}</td>
            </tr>
            <tr class="summary">
                <td><strong>LABA / RUGI BERSIH</strong></td>
                <td class="text-right">
                    <span class="{{ $status === 'profit' ? 'profit' : 'loss' }}">
                        Rp {{ number_format($net_profit, 0, ',', '.') }}
                    </span>
                </td>
            </tr>
        </tbody>
    </table>
</body>
</html>
