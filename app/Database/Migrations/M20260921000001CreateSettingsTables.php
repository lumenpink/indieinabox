<?php

declare(strict_types=1);

namespace Indieinabox\Database\Migrations;

use Indieinabox\Database\Contracts\MigrationInterface;
use PDO;

/**
 * Migration creating settings, content kinds, translations, and initial seeds.
 */
class M20260921000001CreateSettingsTables implements MigrationInterface
{
    public function up(PDO $db): void
    {
        $db->exec('CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT)');
        $db->exec('CREATE TABLE IF NOT EXISTS kinds (kind_key TEXT PRIMARY KEY, config_json TEXT)');
        $db->exec('CREATE TABLE IF NOT EXISTS translations (id INTEGER PRIMARY KEY AUTOINCREMENT, lang TEXT, phrase_key TEXT, phrase_value TEXT)');
        $db->exec('CREATE TABLE IF NOT EXISTS url_translations (id INTEGER PRIMARY KEY AUTOINCREMENT, lang TEXT, slug_key TEXT, slug_value TEXT)');

        // Seed basic settings
        $settings = [
            'base' => '/',
            'sitename' => 'A simple generic blog',
            'fqdn' => 'http://localhost:8081',
            'author' => 'Author Name',
            'buildall' => '1',
            'outputdir' => 'public',
            'contentdir' => 'content',
            'lang' => '["en"]',
            'defaultlang' => 'en',
            'support' => '["md","txt","html","htm"]',
            'htmlpostprocessing' => 'minify',
            'prettylinks' => '1',
            'defaultcategory' => 'General',
            'dev' => '',
            'twtxt' => '{"nick":"author","description":"","avatar":"","following":[],"hubs":[]}',
            'copyright' => '',
            'intl' => json_encode([
                'en' => [
                    'localizeddate' => [
                        'date' => 'F d, Y',
                        'time' => 'h:i A',
                        'full' => 'l, F d, Y \a\t h:i A',
                        'shortdate' => 'm/d/Y',
                        'shorttime' => 'h:i A',
                        'shortfull' => 'm/d/Y h:i A',
                        'daysofweek' => ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'],
                        'months' => ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'],
                    ],
                ],
            ], JSON_UNESCAPED_SLASHES),
            'originaldaysofweek' => '["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"]',
            'originalmonths' => '["January","February","March","April","May","June","July","August","September","October","November","December"]',
            'kindspath' => '{"article":["articles"], "note":["notes"], "garden":["garden","gardens"], "reply":["replies"], "like":["likes"], "repost":["reposts"], "bookmark":["bookmarks"], "photo":["photos"], "video":["videos"], "checkin":["checkins"], "rsvp":["rsvps"], "listen":["listens"], "watch":["watches"], "read":["reads"]}',
        ];

        $stmt = $db->prepare('INSERT OR REPLACE INTO settings (key, value) VALUES (?, ?)');
        foreach ($settings as $k => $v) {
            $stmt->execute([$k, $v]);
        }

        // Seed content kinds
        $kinds = [
            'article' => '{"content_dir":"articles","title":{"en":"Articles"},"palette":{"bg":"#FDF6E3","fg":"#3A2E2A"},"has_title":true,"show_on_home":true,"display_mode":"default"}',
            'note' => '{"content_dir":"notes","title":{"en":"Notes"},"palette":{"bg":"#F5F5F5","fg":"#333333"},"has_title":false,"show_on_home":true,"display_mode":"default"}',
            'garden' => '{"content_dir":"garden","title":{"en":"Digital Garden"},"palette":{"bg":"#E8F5E9","fg":"#1B5E20"},"has_title":true,"show_on_home":true,"display_mode":"default"}',
            'reply' => '{"content_dir":"replies","title":{"en":"Replies"},"palette":{"bg":"#E3F2FD","fg":"#0D47A1"},"has_title":false,"show_on_home":true,"display_mode":"default"}',
            'like' => '{"content_dir":"likes","title":{"en":"Likes"},"palette":{"bg":"#FCE4EC","fg":"#880E4F"},"has_title":false,"show_on_home":true,"display_mode":"default"}',
            'repost' => '{"content_dir":"reposts","title":{"en":"Reposts"},"palette":{"bg":"#F3E5F5","fg":"#4A148C"},"has_title":false,"show_on_home":true,"display_mode":"default"}',
            'bookmark' => '{"content_dir":"bookmarks","title":{"en":"Bookmarks"},"palette":{"bg":"#FFF8E1","fg":"#FF6F00"},"has_title":true,"show_on_home":true,"display_mode":"default"}',
            'photo' => '{"content_dir":"photos","title":{"en":"Photos"},"palette":{"bg":"#FAFAFA","fg":"#212121"},"has_title":false,"show_on_home":true,"display_mode":"default"}',
            'video' => '{"content_dir":"videos","title":{"en":"Videos"},"palette":{"bg":"#ECEFF1","fg":"#263238"},"has_title":false,"show_on_home":true,"display_mode":"default"}',
            'checkin' => '{"content_dir":"checkins","title":{"en":"Checkins"},"palette":{"bg":"#E0F7FA","fg":"#006064"},"has_title":false,"show_on_home":true,"display_mode":"default"}',
            'rsvp' => '{"content_dir":"rsvps","title":{"en":"RSVPs"},"palette":{"bg":"#FBE9E7","fg":"#BF360C"},"has_title":false,"show_on_home":true,"display_mode":"default"}',
            'listen' => '{"content_dir":"listens","title":{"en":"Listens"},"palette":{"bg":"#EDE7F6","fg":"#4527A0"},"has_title":true,"show_on_home":true,"display_mode":"default"}',
            'watch' => '{"content_dir":"watches","title":{"en":"Watches"},"palette":{"bg":"#E8EAF6","fg":"#1A237E"},"has_title":true,"show_on_home":true,"display_mode":"default"}',
            'read' => '{"content_dir":"reads","title":{"en":"Reads"},"palette":{"bg":"#EFEBE9","fg":"#3E2723"},"has_title":true,"show_on_home":true,"display_mode":"default"}',
        ];

        $kindStmt = $db->prepare('INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES (?, ?)');
        foreach ($kinds as $k => $c) {
            $kindStmt->execute([$k, $c]);
        }

        // Seed translations
        $translations = [
            'Notes' => 'Notes',
            'Digital Garden' => 'Digital Garden',
            'Replies' => 'Replies',
            'Likes' => 'Likes',
            'Reposts' => 'Reposts',
            'Bookmarks' => 'Bookmarks',
            'Photos' => 'Photos',
            'Videos' => 'Videos',
            'Checkins' => 'Checkins',
            'RSVPs' => 'RSVPs',
            'Listens' => 'Listens',
            'Watches' => 'Watches',
            'Reads' => 'Reads',
            'Home' => 'Home',
            'Index' => 'Index',
            'Now' => 'Now',
            'now' => 'now',
            'about-the-blog' => 'about-the-blog',
            'Recent posts' => 'Recent posts',
            'Browse the sections of the site in Gopher style:' => 'Browse the sections of the site in Gopher style:',
            'Articles' => 'Articles',
            'About' => 'About',
            'Maturity' => 'Maturity',
        ];

        $transStmt = $db->prepare('INSERT INTO translations (lang, phrase_key, phrase_value) VALUES (?, ?, ?)');
        foreach ($translations as $k => $v) {
            $transStmt->execute(['en', $k, $v]);
        }

        $urlTransStmt = $db->prepare('INSERT INTO url_translations (lang, slug_key, slug_value) VALUES (?, ?, ?)');
        $urlTransStmt->execute(['en', 'index', 'index']);
    }

    public function down(PDO $db): void
    {
        $db->exec('DROP TABLE IF EXISTS url_translations');
        $db->exec('DROP TABLE IF EXISTS translations');
        $db->exec('DROP TABLE IF EXISTS kinds');
        $db->exec('DROP TABLE IF EXISTS settings');
    }
}
