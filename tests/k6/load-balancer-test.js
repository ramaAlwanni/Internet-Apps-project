import http from 'k6/http';
import { check } from 'k6';
import { Counter } from 'k6/metrics';

const baseUrl = (__ENV.API_BASE_URL || 'http://loadbalancer').replace(/\/+$/, '');
const totalRequests = Number(__ENV.TEST_ITERATIONS || 500);
const vus = Number(__ENV.TEST_VUS || 10);

// Tagged points are parsed by scripts/verify-scalability.ps1. k6's built-in
// summary does not expose tag breakdowns, so the wrapper does the assertion.
const backendRequests = new Counter('backend_requests');

export const options = {
  scenarios: {
    load_balancer_verification: {
      executor: 'shared-iterations',
      vus,
      iterations: totalRequests,
      maxDuration: '2m',
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
  });

  let hostname = response.headers['X-Backend-Instance'] || 'unknown';
  try {
    hostname = response.json('hostname') || hostname;
  } catch {
    // Keep the header value; the status check below reports malformed bodies.
  }

  backendRequests.add(1, { backend: hostname, test: 'distribution' });

  check(response, {
    'Load Balancer returns HTTP 200': (res) => res.status === 200,
    'Response identifies a backend': () => hostname !== 'unknown',
  });
}
