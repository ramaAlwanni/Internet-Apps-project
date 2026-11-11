import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '10s', target: 20 }, 
    { duration: '30s', target: 50 }, 
    { duration: '10s', target: 0 },  
  ],
  thresholds: {
    http_req_duration: ['p(95)<500'], 
  },
};

let savedEtag = null;

export default function () {
  const url = 'http://127.0.0.1:8000/api/v1/products?category_id=1&per_page=1';

  const headers = savedEtag ? { 'If-None-Match': savedEtag } : {};
  
  let res = http.get(url, { headers: headers });

  if (res.headers['Etag']) {
    savedEtag = res.headers['Etag'];
  }

  check(res, {
    'status is 200 (Cache Miss/Hit) or 304 (Not Modified)': (r) => r.status === 200 || r.status === 304,
    'response time is fast': (r) => r.timings.duration < 300,
  });

  sleep(1);
}