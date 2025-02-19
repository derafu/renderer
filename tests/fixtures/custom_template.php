<h1><?= $title ?></h1>
<p><?= $content ?></p>
<p>Hoy es <?= $format_as($date, 'date') ?>.</p>
<p>Arreglo a string <?= $to_string(['key1' => 'value1', 'key2' => 'value2', 'key3' => [1, 2, 3]]) ?></p>
