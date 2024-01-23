CREATE TABLE issue (
id bigint,
title text,
body text,
number integer,
comments integer,
repo_id bigint,
user_id bigint,
assignee_id bigint,
created_at timestamp,
updated_at timestamp,
closed_at timestamp,
is_pull_request bool,
reactions json,
label_ids multi64,
assignee_ids multi64
) html_strip='1';

CREATE TABLE comment (
id bigint,
body text,
repo_id bigint,
issue_id bigint,
user_id bigint,
created_at timestamp,
updated_at timestamp,
reactions json
) html_strip='1';

CREATE TABLE user (
id bigint,
login string attribute,
avatar_url string attribute
);

CREATE TABLE repo (
id bigint,
issues integer,
pull_requests integer,
expected_issues integer,
comments integer,
updated_at timestamp,
is_indexing bool,
org string attribute,
name string attribute
);
CREATE TABLE notification (
id bigint,
repo_id bigint,
created_at timestamp,
updated_at timestamp,
is_sent bool,
email string attribute
);
 CREATE TABLE label (
id bigint,
description text,
name string attribute,
color string attribute
);
