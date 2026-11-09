#!/bin/bash
set -euo pipefail

sql_escape() {
    printf '%s' "$1" | sed -e 's/\\/\\\\/g' -e "s/'/''/g"
}

replication_user="$(sql_escape "${MYSQL_REPLICATION_USER:?MYSQL_REPLICATION_USER is required}")"
replication_password="$(sql_escape "${MYSQL_REPLICATION_PASSWORD:?MYSQL_REPLICATION_PASSWORD is required}")"

case "${MYSQL_REPLICATION_ROLE:?MYSQL_REPLICATION_ROLE is required}" in
    primary)
        mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" --execute "
            CREATE USER IF NOT EXISTS '${replication_user}'@'%' IDENTIFIED BY '${replication_password}';
            ALTER USER '${replication_user}'@'%' IDENTIFIED BY '${replication_password}';
            GRANT REPLICATION SLAVE, REPLICATION CLIENT ON *.* TO '${replication_user}'@'%';
        "
        ;;
    replica)
        attempt=0
        until mysql --connect-timeout=5 -h mysql-primary -P 3306 \
            -u"${MYSQL_REPLICATION_USER}" -p"${MYSQL_REPLICATION_PASSWORD}" \
            --execute 'SELECT 1' >/dev/null 2>&1; do
            attempt=$((attempt + 1))
            if [ "$attempt" -ge 60 ]; then
                echo "Timed out waiting for the MySQL primary replication account." >&2
                exit 1
            fi
            sleep 2
        done

        mysql --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD}" --execute "
            CHANGE REPLICATION SOURCE TO
                SOURCE_HOST = 'mysql-primary',
                SOURCE_PORT = 3306,
                SOURCE_USER = '${replication_user}',
                SOURCE_PASSWORD = '${replication_password}',
                SOURCE_AUTO_POSITION = 1;
            START REPLICA;
            SET PERSIST read_only = ON;
            SET PERSIST super_read_only = ON;
        "
        ;;
    *)
        echo "Unsupported MYSQL_REPLICATION_ROLE: ${MYSQL_REPLICATION_ROLE}" >&2
        exit 1
        ;;
esac
