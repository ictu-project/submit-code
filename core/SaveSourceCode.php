<?php

class SaveSourceCode
{

    private function create($prefix)
    {
        $sql = "CREATE TABLE IF NOT EXISTS " . $prefix . "submit (
                submit_id bigint PRIMARY KEY AUTO_INCREMENT NOT NULL,
                post_id bigint(20) UNSIGNED	,
                user_id bigint(20) UNSIGNED	,
                author text,
                user_email text,
                source text,
                pass text,
                total int,
                correct int,
                language text,
                time datetime,
                CONSTRAINT post_id FOREIGN KEY (post_id) REFERENCES " . $prefix . "posts(ID),
                CONSTRAINT user_id FOREIGN KEY (user_id) REFERENCES " . $prefix . "wp_users(ID)
            )";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);
    }

    function save($post_id, $author, $email, $source, $user_id, $pass, $lang)
    {
        require '../../../../wp-config.php';
        $time = current_time('Y-m-d H:i:s');

        global $wpdb;
        $prefix = $wpdb->prefix;
        $this->create($prefix);

        $total = strstr($pass, '/');
        $total = trim($total, '/');

        $correct = stristr($pass, '/', true);
        $correct = trim($correct, '/');

        $data = array(
            'post_id' => $post_id,
            'user_id' => $user_id,
            'author' => $author,
            'user_email' => $email,
            'source' => $source,
            'time' => $time,
            'pass' => $pass,
            'total' => $total,
            'correct' => $correct,
            'language' => $lang
        );

        $insert = $wpdb->insert($prefix.'submit', $data);

        if ($insert != false)
            return true;
        else
            return false;
    }

}