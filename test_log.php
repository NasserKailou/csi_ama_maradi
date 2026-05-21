<?php
error_log('[TEST] Message de test ' . date('H:i:s'));
echo 'error_log() vient d\'être appelé.<br>';
echo 'Chemin configuré : <b>' . ini_get('error_log') . '</b><br>';
echo 'log_errors : <b>' . ini_get('log_errors') . '</b><br>';
echo 'Fichier php.ini chargé : <b>' . php_ini_loaded_file() . '</b>';
