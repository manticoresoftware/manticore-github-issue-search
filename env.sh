#!/usr/bin/env bash
current_path=$(pwd)
PROJECT_DIR=$current_path
export PROJECT=${PROJECT:-"${current_path##*/}"} \
  PROJECT_DIR \
  APP_DIR=$PROJECT_DIR/app \
  STATIC_DIR=$PROJECT_DIR/app/static \
  CONFIG_DIR=$PROJECT_DIR/env/etc \
  ENV_DIR=$PROJECT_DIR/env \
  PROJECT_ENV=${PROJECT_ENV:-"$(test -f $PROJECT_DIR/env/etc/environment && cat $_ || echo 'dev')"} \
  PROJECT_REV=${PROJECT_REV:-"dev"} \
  BIN_DIR=$PROJECT_DIR/env/bin \
  RUN_DIR=$PROJECT_DIR/env/run \
  LOG_DIR=$PROJECT_DIR/env/log \
  VAR_DIR=$PROJECT_DIR/env/var \
  TMP_DIR=$PROJECT_DIR/env/tmp \
  KISS_CORE=$PROJECT_DIR/app/core.php

if [[ "$PATH" != *"$PROJECT_DIR/env/bin"* ]]; then
  export PATH=$PROJECT_DIR:$PROJECT_DIR/env/bin:$PATH
fi
