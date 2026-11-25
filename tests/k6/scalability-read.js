import http from 'k6/http';
import { check } from 'k6';

const baseUrl = (__ENV.API_BASE_URL || 'http://loadbalancer').replace(/\/+$/, '');

export const options = {
  discardResponseBodies: true,
  scenarios: {
    concurrent_read_test: {
      executor: 'shared-iterations',
      vus: Number(__ENV.TEST_VUS || 20),
      iterations: Number(__ENV.TEST_ITERATIONS || 500),
      maxDuration: '3m',
    },
  },
  thresholds: {
    checks: ['rate==1'],
    http_req_failed: ['rate==0'],
    http_req_duration: ['p(95)<3000'],
  },
};

export default function () {
  const response = http.get(baseUrl, {
    headers: { 'User-Agent': 'k6-scalability-read' },
    tags: { test_name: 'scalability_read' },
  });

  check(response, {
    'status is 200': (r) => r.status === 200,
  });
}
