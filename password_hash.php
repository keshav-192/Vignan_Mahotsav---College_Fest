<?php
$plain = 'Keshav@9284';
$hash = password_hash($plain, PASSWORD_DEFAULT);
echo $hash;