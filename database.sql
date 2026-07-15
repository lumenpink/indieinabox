CREATE TABLE IF NOT EXISTS settings (key TEXT PRIMARY KEY, value TEXT);
CREATE TABLE IF NOT EXISTS kinds (kind_key TEXT PRIMARY KEY, config_json TEXT);
CREATE TABLE IF NOT EXISTS translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT, lang TEXT, phrase_key TEXT, phrase_value TEXT
);
CREATE TABLE IF NOT EXISTS url_translations (
    id INTEGER PRIMARY KEY AUTOINCREMENT, lang TEXT, slug_key TEXT, slug_value TEXT
);
CREATE TABLE IF NOT EXISTS microsub_channels (uid TEXT PRIMARY KEY, name TEXT);
CREATE TABLE IF NOT EXISTS microsub_subscriptions (
    id INTEGER PRIMARY KEY AUTOINCREMENT, channel_uid TEXT, url TEXT, type TEXT, name TEXT, photo TEXT
);

INSERT OR IGNORE INTO microsub_channels (uid, name) VALUES ('inbox', 'Timeline');
INSERT OR IGNORE INTO microsub_channels (uid, name) VALUES ('notifications', 'Notifications');

INSERT OR REPLACE INTO settings (key, value) VALUES ('base', '/');
INSERT OR REPLACE INTO settings (key, value) VALUES ('title', 'My Blog');
INSERT OR REPLACE INTO settings (key, value) VALUES ('sitename', 'A simple generic blog');
INSERT OR REPLACE INTO settings (key, value) VALUES ('fqdn', 'http://localhost:8081');
INSERT OR REPLACE INTO settings (key, value) VALUES ('author', 'Author Name');
INSERT OR REPLACE INTO settings (key, value) VALUES ('buildall', '1');
INSERT OR REPLACE INTO settings (key, value) VALUES ('outputdir', 'public');
INSERT OR REPLACE INTO settings (key, value) VALUES ('contentdir', 'content');
INSERT OR REPLACE INTO settings (key, value) VALUES ('lang', '["en"]');
INSERT OR REPLACE INTO settings (key, value) VALUES ('defaultlang', 'en');
INSERT OR REPLACE INTO settings (key, value) VALUES ('support', '["md","txt","html","htm"]');
INSERT OR REPLACE INTO settings (key, value) VALUES ('htmlpostprocessing', 'minify');
INSERT OR REPLACE INTO settings (key, value) VALUES ('prettylinks', '1');
INSERT OR REPLACE INTO settings (key, value) VALUES ('defaultcategory', 'General');
INSERT OR REPLACE INTO settings (key, value) VALUES ('dev', '');
INSERT OR REPLACE INTO settings (key, value) VALUES ('twtxt', 
    '{"nick":"author","description":"","avatar":"","following":[],"hubs":[]}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('article', 
    '{"content_dir":"articles","title":{"en":"Articles"},"palette":{"bg":"#FDF6E3","fg":"#3A2E2A"},' ||
    '"has_title":true,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('note', 
    '{"content_dir":"notes","title":{"en":"Notes"},"palette":{"bg":"#F5F5F5","fg":"#333333"},' ||
    '"has_title":false,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('garden', 
    '{"content_dir":"garden","title":{"en":"Digital Garden"},"palette":{"bg":"#E8F5E9","fg":"#1B5E20"},' ||
    '"has_title":true,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('reply', 
    '{"content_dir":"replies","title":{"en":"Replies"},"palette":{"bg":"#E3F2FD","fg":"#0D47A1"},' ||
    '"has_title":false,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('like', 
    '{"content_dir":"likes","title":{"en":"Likes"},"palette":{"bg":"#FCE4EC","fg":"#880E4F"},' ||
    '"has_title":false,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('repost', 
    '{"content_dir":"reposts","title":{"en":"Reposts"},"palette":{"bg":"#F3E5F5","fg":"#4A148C"},' ||
    '"has_title":false,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('bookmark', 
    '{"content_dir":"bookmarks","title":{"en":"Bookmarks"},"palette":{"bg":"#FFF8E1","fg":"#FF6F00"},' ||
    '"has_title":true,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('photo', 
    '{"content_dir":"photos","title":{"en":"Photos"},"palette":{"bg":"#FAFAFA","fg":"#212121"},' ||
    '"has_title":false,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('video', 
    '{"content_dir":"videos","title":{"en":"Videos"},"palette":{"bg":"#ECEFF1","fg":"#263238"},' ||
    '"has_title":false,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('checkin', 
    '{"content_dir":"checkins","title":{"en":"Checkins"},"palette":{"bg":"#E0F7FA","fg":"#006064"},' ||
    '"has_title":false,"show_on_home":true,"display_mode":"default"}'
);
INSERT OR REPLACE INTO kinds (kind_key, config_json) VALUES ('rsvp', 
    '{"content_dir":"rsvps","title":{"en":"RSVPs"},"palette":{"bg":"#FBE9E7","fg":"#BF360C"},' ||
    '"has_title":false,"show_on_home":true,"display_mode":"default"}'
);
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Notes', 'Notes');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Digital Garden', 'Digital Garden');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Replies', 'Replies');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Likes', 'Likes');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Reposts', 'Reposts');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Bookmarks', 'Bookmarks');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Photos', 'Photos');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Videos', 'Videos');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Checkins', 'Checkins');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'RSVPs', 'RSVPs');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Home', 'Home');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Index', 'Index');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Now', 'Now');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'now', 'now');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'about-the-blog', 'about-the-blog');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Recent posts', 'Recent posts');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 
    'Browse the sections of the site in Gopher style:', 'Browse the sections of the site in Gopher style:'
);
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Articles', 'Articles');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'About', 'About');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Maturity', 'Maturity');
INSERT INTO translations (lang, phrase_key, phrase_value) VALUES ('en', 'Reliability', 'Reliability');
INSERT INTO url_translations (lang, slug_key, slug_value) VALUES ('en', 'index', 'index');
INSERT OR REPLACE INTO settings (key, value) VALUES ('copyright', '');

INSERT INTO settings (key, value) VALUES ('intl', '{"en":{"localizeddate":{"date":"F d, Y","time":"h:i A",' ||
    '"full":"l, F d, Y \\a\\t h:i A","shortdate":"m\/d\/Y","shorttime":"h:i A","shortfull":"m\/d\/Y h:i A",' ||
    '"daysofweek":["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"],' ||
    '"months":["January","February","March","April","May","June","July","August","September","October",' ||
    '"November","December"]}}}'
);
INSERT INTO settings (key, value) VALUES ('originaldaysofweek', 
    '["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"]'
);
INSERT INTO settings (key, value) VALUES ('originalmonths', 
    '["January","February","March","April","May","June","July","August",' ||
    '"September","October","November","December"]'
);

INSERT INTO settings (key, value) VALUES ('kindspath', '{"article":["articles"], "note":["notes"], "garden":["garden"], "reply":["replies"], "like":["likes"], "repost":["reposts"], "bookmark":["bookmarks"], "photo":["photos"], "video":["videos"], "checkin":["checkins"], "rsvp":["rsvps"]}');

CREATE TABLE IF NOT EXISTS indieauth_codes (
    code_hash TEXT PRIMARY KEY,
    client_id TEXT NOT NULL,
    redirect_uri TEXT NOT NULL,
    state TEXT,
    scope TEXT,
    code_challenge TEXT,
    code_challenge_method TEXT,
    expires_at INTEGER NOT NULL,
    me TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS indieauth_tokens (
    token_hash TEXT PRIMARY KEY,
    client_id TEXT NOT NULL,
    scope TEXT,
    me TEXT NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS activitypub_followers (
    actor_url TEXT PRIMARY KEY,
    inbox_url TEXT NOT NULL,
    shared_inbox_url TEXT
);

CREATE TABLE IF NOT EXISTS activitypub_keys (
    key_id TEXT PRIMARY KEY,
    private_key TEXT NOT NULL,
    public_key TEXT NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS activitypub_outbox (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    payload_json TEXT NOT NULL,
    target_inbox TEXT NOT NULL,
    status TEXT DEFAULT 'pending',
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS activitypub_actors (
    actor_url TEXT PRIMARY KEY,
    public_key TEXT NOT NULL,
    updated_at INTEGER NOT NULL
);

INSERT OR REPLACE INTO settings (key, value) VALUES ('activitypub_handle', 'author');

CREATE TABLE IF NOT EXISTS inbox_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    type TEXT NOT NULL,
    payload_json TEXT NOT NULL,
    created_at INTEGER NOT NULL
);

CREATE TABLE IF NOT EXISTS archive_queue (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL,
    status TEXT DEFAULT 'pending',
    requested_at INTEGER NOT NULL,
    force_archive INTEGER DEFAULT 0
);

CREATE TABLE IF NOT EXISTS archived_links (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    url TEXT NOT NULL,
    timestamp INTEGER NOT NULL,
    local_pdf_path TEXT,
    archive_org_url TEXT
);

CREATE TABLE IF NOT EXISTS archive_aliases (
    original_url TEXT PRIMARY KEY,
    final_url TEXT NOT NULL
);
