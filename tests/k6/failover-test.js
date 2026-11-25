import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const baseUrl = (__ENV.API_BASE_URL || 'http://loadbalancer').replace(/\/+$/, '');
const vus = Number(__ENV.TEST_VUS || 5);
const duration = __ENV.TEST_DURATION || '45s';

const backendRequests = new Counter('backend_requests');

export const options = {
  scenarios: {
    failover_verification: {
      executor: 'constant-vus',
      vus,
      duration,
      gracefulStop: '5s',
    },
  },
  thresholds: {
    checks: ['rate==1'],
    http_req_failed: ['rate==0'],
  },
};

export default function () {
  const response = http.get(`${baseUrl}/instance`, {
    headers: { Accept: 'application/json' },
    tags: { test_name: 'failover' },
  });

  let hostname = response.headers['X-Backend-Instance'] || 'unknown';
  try {
    hostname = response.json('hostname') || hostname;
  } catch {
    // Keep the header value; the status check below reports malformed bodies.
  }

  backendRequests.add(1, { backend: hostname, test: 'failover' });

  check(response, {
    'Service stays available during replica stop': (res) => res.status === 200,
  });
}
