# Confronto Gate 3/4: preventivi del sito vivo (templines_calc_total)
# vs API locale (/api/quote) sulle stesse combinazioni veicolo/date/localitÃ .
$ErrorActionPreference = 'Stop'

$LiveUrl = 'https://www.easyrent24h.com/wp-admin/admin-ajax.php'
$LocalUrl = 'http://localhost:8000/api/quote'
$UA = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36'

function Get-Amount([string]$html) {
    if (-not $html) { return $null }
    $m = [regex]::Match(($html -replace '&nbsp;', ' '), '([\d.,]+)')
    if (-not $m.Success) { return $null }
    $raw = $m.Groups[1].Value
    # wc_price: migliaia con . o , e decimali finali a 2 cifre
    $norm = $raw -replace '[.,](?=\d{3}\b)', ''
    $norm = $norm -replace ',', '.'
    return [double]$norm
}

function Invoke-Live($params) {
    $resp = Invoke-RestMethod -Uri $LiveUrl -Method Post -Body $params -UserAgent $UA -TimeoutSec 60
    return $resp
}

function Invoke-Local($body) {
    return Invoke-RestMethod -Uri $LocalUrl -Method Post -Body ($body | ConvertTo-Json) -ContentType 'application/json' -TimeoutSec 30
}

# combo: nome, parametri live (id legacy, date GG.MM.AAAA), parametri locali (id nuovi, date ISO)
$cases = @(
    @{
        name = '1) Liberty Agerola 21-23/09 senza orari (regola giorno in meno)'
        live = @{ action = 'templines_calc_total'; vehicle_id = 3166; start = '21.09.2026'; end = '23.09.2026'; pick_up = 216; drop_off = 216; quantity = 1; lang = 'it' }
        local = @{ vehicle_id = 1; start = '2026-09-21'; end = '2026-09-23'; pick_up = 2; drop_off = 2; quantity = 1 }
    },
    @{
        name = '2) Liberty Agerola 21-23/09 riconsegna 10:00 (giorno pieno)'
        live = @{ action = 'templines_calc_total'; vehicle_id = 3166; start = '21.09.2026'; end = '23.09.2026'; pick_up = 216; drop_off = 216; quantity = 1; fasciaOraStart = '10:00'; fasciaOraEnd = '10:00'; lang = 'it' }
        local = @{ vehicle_id = 1; start = '2026-09-21'; end = '2026-09-23'; pick_up = 2; drop_off = 2; quantity = 1; time_start = '10:00'; time_end = '10:00' }
    },
    @{
        name = '3) Vespa Tiffany Positano 22/09 giornata singola (fasce dalle 09:00)'
        live = @{ action = 'templines_calc_total'; vehicle_id = 3168; start = '22.09.2026'; end = '22.09.2026'; pick_up = 164; drop_off = 164; quantity = 1; lang = 'it' }
        local = @{ vehicle_id = 2; start = '2026-09-22'; end = '2026-09-22'; pick_up = 1; drop_off = 1; quantity = 1 }
    },
    @{
        name = '4) Liberty Amalfi 21-23/09 (fasce solo 08:00/20:00, delivery 15)'
        live = @{ action = 'templines_calc_total'; vehicle_id = 3166; start = '21.09.2026'; end = '23.09.2026'; pick_up = 238; drop_off = 238; quantity = 1; lang = 'it' }
        local = @{ vehicle_id = 1; start = '2026-09-21'; end = '2026-09-23'; pick_up = 5; drop_off = 5; quantity = 1 }
    },
    @{
        name = '5) Smart Fortwo Agerola 21-24/09 riconsegna 18:00 (4 giorni)'
        live = @{ action = 'templines_calc_total'; vehicle_id = 11314; start = '21.09.2026'; end = '24.09.2026'; pick_up = 216; drop_off = 216; quantity = 1; fasciaOraStart = '10:00'; fasciaOraEnd = '18:00'; lang = 'it' }
        local = @{ vehicle_id = 18; start = '2026-09-21'; end = '2026-09-24'; pick_up = 2; drop_off = 2; quantity = 1; time_start = '10:00'; time_end = '18:00' }
    }
)

foreach ($case in $cases) {
    Write-Host "=== $($case.name)" -ForegroundColor Cyan
    try {
        $live = Invoke-Live $case.live
        $liveTotal = Get-Amount ($(if ($live.total) { $live.total } else { $live.price }))
        $liveFasce = ''
        if ($live.fasciaStart) { $liveFasce = "start[$($live.fasciaStart[0])..$($live.fasciaStart[-1]) n=$($live.fasciaStart.Count)] end[$($live.fasciaEnd[0])..$($live.fasciaEnd[-1]) n=$($live.fasciaEnd.Count)]" }
        Write-Host ("LIVE : days=$($live.days) total=$liveTotal $liveFasce")
        if ($live.custom_message) { Write-Host ("LIVE msg: $($live.custom_message)") }
    } catch {
        Write-Host "LIVE : ERRORE $($_.Exception.Message)" -ForegroundColor Red
        $live = $null
    }
    try {
        $loc = Invoke-Local $case.local
        $locFasce = ''
        if ($loc.start_slots) { $locFasce = "start[$($loc.start_slots[0])..$($loc.start_slots[-1]) n=$($loc.start_slots.Count)] end[$($loc.end_slots[0])..$($loc.end_slots[-1]) n=$($loc.end_slots.Count)]" }
        Write-Host ("LOCAL: days=$($loc.days) total=$($loc.total) $locFasce")
        if ($loc.message) { Write-Host ("LOCAL msg: $($loc.message)") }
    } catch {
        Write-Host "LOCAL: ERRORE $($_.Exception.Message)" -ForegroundColor Red
    }
    if ($live -and $live.extra) {
        $extraSummary = ($live.extra.PSObject.Properties | ForEach-Object { "$($_.Name)=$(Get-Amount $_.Value)" }) -join ' '
        Write-Host "LIVE extra: $extraSummary"
    }
    Write-Host ''
}
