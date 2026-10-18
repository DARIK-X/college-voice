@extends('election.layout')
@section('title','Результаты')
@section('content')
<section class="page-intro"><span class="eyebrow"><span class="live-dot"></span> РЕЗУЛЬТАТЫ В РЕАЛЬНОМ ВРЕМЕНИ</span><h1>Каждый голос<br><em>меняет картину.</em></h1><p>Распределение голосов обновляется автоматически.</p></section><section class="results-layout" id="results"><div class="chart-panel"><span class="eyebrow">ОБЩАЯ КАРТИНА</span><div id="donut" class="donut"><div><strong id="chart-share">—</strong><span>распределение</span></div></div><p id="connection" role="status">Загружаем результаты…</p><p id="result-status" class="muted"></p></div><div class="ranking-panel"><h2>Распределение голосов</h2><div id="ranking" aria-live="off"></div><p class="muted result-note">Проценты рассчитаны от всех учтённых голосов. Округление до 0,1%.</p></div></section><a class="text-link" href="/">← К списку кандидатов</a>
@endsection
