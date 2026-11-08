FROM mysql:8.4

COPY docker/mysql/replication-init.sh /docker-entrypoint-initdb.d/99-replication.sh

RUN chmod 0755 /docker-entrypoint-initdb.d/99-replication.sh
