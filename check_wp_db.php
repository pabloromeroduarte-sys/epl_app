<?php
$wp = new PDO('mysql:host=localhost;dbname=wp_epl_import;charset=utf8mb4', 'root', '');
$res = $wp->query("SHOW CREATE TABLE wpqu_posts")->fetch(PDO::FETCH_ASSOC);
print_r($res);
$res2 = $wp->query("SELECT post_title FROM wpqu_posts WHERE post_title LIKE '%Mart%' LIMIT 5")->fetchAll(PDO::FETCH_ASSOC);
print_r($res2);
