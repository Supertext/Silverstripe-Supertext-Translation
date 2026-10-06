#!/usr/bin/env bash
# CI: starts the demo image twice against MySQL with the stand-in API, checks the demo accounts
# (created once, never duplicated, passwords never logged) and translates the sample article as
# the editor. Needs: docker image supertext-silverstripe-demo, MySQL at $MYSQL_URL (root), the
# `mysql` client, and the stand-in on 127.0.0.1:8765.
set -euo pipefail
PORT=8080
B=http://127.0.0.1:$PORT
DB=silverstripe
export DEMO_ADMIN_EMAIL=ci-admin@example.com DEMO_ADMIN_PASSWORD="Ci-$(openssl rand -hex 12)"
export DEMO_EDITOR_EMAIL=ci-editor@example.com DEMO_EDITOR_PASSWORD="Ci-$(openssl rand -hex 12)"
q() { mysql ${MYSQL_ARGS:--h 127.0.0.1 -P 3306 -uroot -proot} -N --default-character-set=utf8mb4 "$DB" -e "$1"; }
sake() { docker exec demo vendor/bin/sake "$@"; }

start() {
	docker rm -f demo >/dev/null 2>&1 || true
	docker run -d --name demo --network host -e PORT=$PORT -e DATABASE_URL="$MYSQL_URL" -e SS_BASE_URL=$B \
		-e DEMO_ADMIN_EMAIL -e DEMO_ADMIN_PASSWORD -e DEMO_EDITOR_EMAIL -e DEMO_EDITOR_PASSWORD \
		-e SUPERTEXT_API_KEY=anything -e SUPERTEXT_API_URL=http://127.0.0.1:8765/v1/ supertext-silverstripe-demo >/dev/null
	for _ in $(seq 90); do curl -sf -o /dev/null $B/Security/login && break; sleep 2; done
	curl -sf -o /dev/null $B/Security/login
	docker logs demo 2>&1 | grep '\[demo\]' || true
}

start
start   # second start: nothing duplicated or changed
docker logs demo 2>&1 | grep > /dev/null 'DEMO_EDITOR: account exists, left unchanged'
logs=$(docker logs demo 2>&1)
if grep -qF -e "$DEMO_ADMIN_PASSWORD" -e "$DEMO_EDITOR_PASSWORD" <<< "$logs"; then echo "A password appeared in the log"; exit 1; fi

test "$(q "select count(*) from Member")" = 2
test "$(q "select g.Code from Member m join Group_Members gm on gm.MemberID=m.ID join \`Group\` g on g.ID=gm.GroupID where m.Email='ci-admin@example.com'")" = administrators
test "$(q "select g.Code from Member m join Group_Members gm on gm.MemberID=m.ID join \`Group\` g on g.ID=gm.GroupID where m.Email='ci-editor@example.com'")" = supertext-editors
test "$(q "select count(*) from Permission p join \`Group\` g on g.ID=p.GroupID where g.Code='supertext-editors' and p.Code in ('SUPERTEXT_TRANSLATE','CMS_ACCESS_CMSMain')")" = 2
test "$(q "select group_concat(Locale order by Sort) from Fluent_Locale")" = "en_US,de_CH,fr_CH,it_CH"
echo "accounts and locales OK"

sake tasks:supertext-check | grep > /dev/null 'The API key works'
PAGE=$(q "select ID from SiteTree where URLSegment='swiss-chocolate-shipped-worldwide'")
sake tasks:supertext-translate --page="$PAGE" --from=en_US --member=ci-editor@example.com | tee /tmp/translate.log
test "$(grep -c ': translated' /tmp/translate.log)" = 3
sake tasks:supertext-translate --page="$PAGE" --from=en_US --to=de_CH --member=ci-editor@example.com | grep > /dev/null 'skipped'

test "$(q "select Title from SiteTree_Localised where RecordID=$PAGE and Locale='de_CH'")" = "Schweizer Schokolade, weltweit versandt"
test "$(q "select URLSegment from SiteTree_Localised where RecordID=$PAGE and Locale='fr_CH'")" = "chocolat-suisse-expedie-dans-le-monde-entier"
q "select HTML from ElementContent_Localised where Locale='de_CH'" | grep > /dev/null '<strong>Berner</strong>'
q "select HTML from ElementContent_Localised where Locale='de_CH'" | grep > /dev/null 'href="https://www.supertext.com"'
test "$(q "select count(*) from Element_Localised where Locale='it_CH'")" = 3
test "$(q "select Title from Element_Localised where Locale='fr_CH' order by RecordID limit 1")" = "De Berne vers le monde"
test "$(q "select count(*) from SupertextTranslation where Status='translated'")" = 3
echo "Demo check passed"
