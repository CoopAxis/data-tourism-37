<?php

class Util
{

    public static function delTree($dir)
    {
        $files = array_diff(scandir($dir), array(
            '.',
            '..'
        ));
        foreach ($files as $file) {
            (is_dir("$dir/$file") && ! is_link($dir)) ? Util::delTree("$dir/$file") : unlink("$dir/$file");
        }
        return rmdir($dir);
    }
}
