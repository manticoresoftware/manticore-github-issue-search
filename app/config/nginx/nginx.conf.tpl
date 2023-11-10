log_format  {{PROJECT}}
  '$remote_addr - $remote_user [$time_local] "$request" '
  '$status $body_bytes_sent "$http_referer" '
  '"$http_user_agent" "$http_x_forwarded_for" '
  '$upstream_response_time sec "$host"';

upstream {{PROJECT}}-fpm {
  server unix:{{RUN_DIR}}/php-fpm.sock;
}

server {
  error_log {{LOG_DIR}}/nginx-error.log;

  listen   {{SERVER_PORT}};
  server_name  {{SERVER_NAME}};
  client_max_body_size {{UPLOAD_MAX_FILESIZE}};

  include {{CONFIG_DIR}}/nginx_route_map.conf;

  set $realm "{{AUTH}}";
  auth_basic $realm;
  auth_basic_user_file {{CONFIG_DIR}}/.htpasswd;

  open_file_cache {{OPEN_FILE_CACHE}}; # disable file cache for development
  root {{STATIC_DIR}};
  location = / {
    if ($request_method !~ ^(GET|HEAD|POST|PUT|DELETE|OPTIONS)$ ) {
      return 444;
    }

    if ($request_method = 'OPTIONS') {
      add_header 'Access-Control-Allow-Origin' '{{CORS_ORIGIN}}' always;
      add_header 'Access-Control-Allow-Credentials' '{{CORS_CREDENTIALS}}' always;
      add_header 'Access-Control-Allow-Methods' '{{CORS_METHODS}}' always;
      add_header 'Access-Control-Allow-Headers' '{{CORS_HEADERS}}' always;
      add_header 'Access-Control-Max-Age' 1728000;
      add_header 'Content-Type' 'text/plain charset=UTF-8';
      add_header 'Content-Length' 0;
      return 204;
    }

    try_files $uri @app;
    log_not_found  off;
    error_log      /dev/null;
  }

  # Common bundles
  location ~* ^/([a-zA-Z0-9]+)\-[0-9A-Za-z]+\.(js|js.map|css|css.map)$ {
    add_header 'X-Content-Type-Options' 'nosniff' always;
    alias {{VAR_DIR}}/$1.$2;
  }

  location @app {
    access_log  {{LOG_DIR}}/nginx-access.log {{PROJECT}} buffer=32k;

    expires off;

    # CORS headers
    add_header 'Access-Control-Allow-Origin' '{{CORS_ORIGIN}}' always;
    add_header 'Access-Control-Allow-Credentials' '{{CORS_CREDENTIALS}}' always;
    add_header 'Access-Control-Allow-Methods' '{{CORS_METHODS}}' always;
    add_header 'Access-Control-Allow-Headers' '{{CORS_HEADERS}}' always;

    include        {{CONFIG_DIR}}/nginx_fastcgi_params;

    fastcgi_param  SCRIPT_FILENAME  {{APP_DIR}}/main.php;
    fastcgi_param  SCRIPT_NAME      {{APP_DIR}}/main.php;
    fastcgi_param  KISS_CORE        {{APP_DIR}}/core.php;
    fastcgi_param  APP_DIR          {{APP_DIR}};
    fastcgi_param  ENV_DIR          {{ENV_DIR}};
    fastcgi_param  CONFIG_DIR       {{CONFIG_DIR}};
    fastcgi_param  PROJECT          {{PROJECT}};
    fastcgi_param  PROJECT_ENV      {{PROJECT_ENV}};
    fastcgi_param  VAR_DIR          {{VAR_DIR}};
    fastcgi_param  LOG_DIR          {{LOG_DIR}};
    fastcgi_param  BIN_DIR          {{BIN_DIR}};
    fastcgi_param  RUN_DIR          {{RUN_DIR}};
    fastcgi_param  TMP_DIR          {{TMP_DIR}};
    fastcgi_param  STATIC_DIR       {{STATIC_DIR}};

    fastcgi_pass   {{PROJECT}}-fpm;
  }

  # This location block is used to view PHP-FPM stats
  location = /php_status {
    deny all;

    include        {{CONFIG_DIR}}/nginx_fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $fastcgi_script_name;
    fastcgi_pass {{PROJECT}}-fpm;
  }
}

# Default server
server {
  listen {{SERVER_PORT}} default_server;
  server_name  _;

  # Stub status
  location = /nginx_status {
    allow 127.0.0.1;
    allow 10.0.0.0/8;
    allow 192.168.0.0/16;
    allow 172.16.0.0/12;
    deny all;

    stub_status on;
    access_log off;
  }

  location = /php_status {
    allow 127.0.0.1;
    allow 10.0.0.0/8;
    allow 192.168.0.0/16;
    allow 172.16.0.0/12;
    deny all;
    access_log off;

    include      {{CONFIG_DIR}}/nginx_fastcgi_params;
    fastcgi_param SCRIPT_FILENAME $fastcgi_script_name;
    fastcgi_pass {{PROJECT}}-fpm;
  }

  location / {
    return 444;
  }
}
