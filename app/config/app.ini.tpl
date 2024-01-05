[common]
; Алфавит для кодирование чисел по словарю
alphabet    = 'nGWZFAQcUxV2fqJtMmyR7BHwPXNrL9DijhCsvuaezpTS3gEdk546Yb8K'
epoch       = 1699417571000
secret      = ''

trigger_map_file   = '{{CONFIG_DIR}}/trigger_event_map.php'
trigger_param_file = '{{CONFIG_DIR}}/trigger_param_map.php'
uri_map_file	     = '{{CONFIG_DIR}}/uri_request_map.php'
param_map_file     = '{{CONFIG_DIR}}/import_var_map.php'
action_map_file    = '{{CONFIG_DIR}}/action_map.php'

upload_max_filesize = '10M'

proto = 'http'
domain = 'manticore-github-issue-search.zz'
type = 'fpm'

lang_type = 'none' ; path or domain or none depends what we use for split
languages[] = 'en'

; 0 - debug
; 1 - warning
; 2 - info
; 3 - no output at all
cli_level = 2

[common:test]
domain = 'test.github.manticoresearch.com'

[common:production]
domain = 'github.manticoresearch.com'

[default]
action = 'home'

[view]
source_dir          = '{{APP_DIR}}/views'
compile_dir         = '{{TMP_DIR}}/views'
template_extension  = 'tpl'
strip_comments      = false
merge_lines         = false

[view:production]
compile_dir    = '{{TMP_DIR}}/{{PROJECT_REV}}/views'
strip_comments = true
merge_lines    = true

[session]
name          = 'KISS'
save_handler  = 'files'
save_depth    = 2 ; this config used only for handler=files
save_path     = "{{TMP_DIR}}/{{PROJECT_REV}}/sessions"

[server]
port = 80
ssl_port = 443

[nginx]
auth_name = 'test'
auth_pass = 'test'
; auth_basic nginx param: off, Restricted
auth = 'off'
open_file_cache = 'off'
use_ssl = 0

[nginx:production]
open_file_cache = 'max=100000 inactive=600s'
use_ssl = 1

[nginx:test]
auth = 'Restricted'

[cors]
origin = '*'
methods = 'GET, POST, PUT, DELETE, OPTIONS'
headers = 'DNT,X-CustomHeader,Keep-Alive,User-Agent,X-Requested-With,If-Modified-Since,Cache-Control,Content-Type'
credentials = 'true'

[queue]
host = 'queue'
port = 4444

[manticore]
host = 'manticore'
port = 9308
