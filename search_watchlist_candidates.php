<?php

$patterns = [
    // Alquimia
    'Frasco de' => 'Alquimia (frasco)',
    'Fíala' => 'Alquimia (fiala de profesión)',
    'Poción de' => 'Alquimia (pocion)',
    'Caldero' => 'Alquimia (caldero)',
    'Sinergista Prodigioso' => 'Alquimia (Wondrous Synergist)',
    'Derivado Estabilizado' => 'Alquimia (Stabilized Derivate)',

    // Inscripcion
    'Misiva' => 'Inscripcion (misiva)',
    'Runa Vantus' => 'Inscripcion (vantus rune)',
    'Contrato' => 'Inscripcion (contrato)',
    'Cifra del Alma' => 'Inscripcion (soul cipher)',
    'Madera Thalasiana' => 'Inscripcion (lumber)',

    // Joyeria
    'Piedra Envuelta en Ocaso' => 'Joyeria (dusk shrouded stone)',
    'Polvo de Gema' => 'Joyeria (gemdust)',

    // Sastreria
    'Hilo de Hojaplata' => 'Sastreria (thread)',

    // Herrería
    'Aleación de Esterlina' => 'Herreria (sterling alloy)',
    'Piedra de Afilar Refulgente' => 'Herreria (whetstone)',

    // Ingenieria
    'Evercore' => 'Ingenieria (evercore)',
    'Aetherlume' => 'Ingenieria (aetherlume)',

    // Cocina
    'Banquete' => 'Cocina (feast)',
    'Filete Thalasiano' => 'Cocina (fish fillet)',
    'Guisado' => 'Cocina (stew)',

    // Recoleccion
    'Loto Nocturno' => 'Herboristeria (Nocturnal Lotus)',
    'Sanguiespina' => 'Herboristeria (Sanguithorn)',
    'Lirio de Maná' => 'Herboristeria (Mana Lily)',
    'Mena de Plata Brillante' => 'Mineria (Brilliant Silver Ore)',
    'Torio Deslumbrante' => 'Mineria (Dazzling Thorium)',

    // Item especifico que ya confirmaste que existe
    'Loa de la suerte' => 'Ya confirmado en analisis previo',
];

$commodityItemIds = App\Models\CommodityPriceHistory::distinct()->pluck('item_id');

foreach ($patterns as $pattern => $label) {
    $matches = App\Models\Item::whereIn('blizzard_id', $commodityItemIds)
        ->where('name', 'ilike', "%{$pattern}%")
        ->limit(10)
        ->get(['blizzard_id', 'name']);

    if ($matches->isEmpty()) {
        echo "[{$label}] '{$pattern}' -> SIN COINCIDENCIAS" . PHP_EOL;
        continue;
    }

    echo "[{$label}] '{$pattern}':" . PHP_EOL;
    foreach ($matches as $m) {
        echo "    id={$m->blizzard_id}  {$m->name}" . PHP_EOL;
    }
}
