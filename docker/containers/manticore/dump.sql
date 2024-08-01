CREATE TABLE label (
id bigint,
description text,
name string attribute,
color string attribute
);
CREATE TABLE notification (
id bigint,
repo_id bigint,
created_at timestamp,
updated_at timestamp,
is_sent bool,
email string attribute
);
CREATE TABLE org (
id bigint,
description text,
public_repos integer,
followers integer,
following integer,
updated_at timestamp,
name string attribute
);
CREATE TABLE repo (
id bigint,
org_id integer,
issues integer,
pull_requests integer,
expected_issues integer,
comments integer,
is_indexing bool,
updated_at timestamp,
name string attribute
) index_field_lengths='1';
CREATE TABLE user (
id bigint,
login string attribute,
avatar_url string attribute
);
