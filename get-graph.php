<?php
$config = require 'config.php';
header('Content-Type: text/plain');
echo file_get_contents($config['graph_file']);