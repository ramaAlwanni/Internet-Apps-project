#!/bin/sh
echo "== raw TCP connect + PING from backend -> redis =="
i=1
while [ $i -le 6 ]; do
  t0=$(date +%s%N)
  exec 3<>/dev/tcp/redis/6379
  printf 'PING\r\n' >&3
  head -c 7 <&3 >/dev/null
  exec 3<&-
  t1=$(date +%s%N)
  echo "  ping $i: $(( (t1-t0)/1000000 ))ms"
  i=$((i+1))
done

echo "== raw TCP connect only (no data) backend -> redis =="
i=1
while [ $i -le 3 ]; do
  t0=$(date +%s%N)
  exec 3<>/dev/tcp/redis/6379
  exec 3<&-
  t1=$(date +%s%N)
  echo "  connect $i: $(( (t1-t0)/1000000 ))ms"
  i=$((i+1))
done

echo "== DNS getent =="
t0=$(date +%s%N); getent hosts redis >/dev/null; t1=$(date +%s%N); echo "  getent redis: $(( (t1-t0)/1000000 ))ms"
t0=$(date +%s%N); getent hosts mysql-replica >/dev/null; t1=$(date +%s%N); echo "  getent mysql-replica: $(( (t1-t0)/1000000 ))ms"

echo "== raw TCP+SELECT 1 from backend -> mysql-replica =="
t0=$(date +%s%N)
exec 3<>/dev/tcp/mysql-replica/3306
exec 3<&-
t1=$(date +%s%N)
echo "  mysql connect: $(( (t1-t0)/1000000 ))ms"
