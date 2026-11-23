import http from 'k6/http';
import { check, fail } from 'k6';
import { Counter } from 'k6/metrics';

const baseUrl = (__ENV.API_BASE_URL || 'http://loadbalancer').replace(/\/+$/, '');
const totalRequests = Number(__ENV.TEST_ITERATIONS || 500);
const vus = Number(__ENV.TEST_VUS || 10);

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

  let body = null;
  try {
    body = response.json();
  } catch {
    // The check below reports the non-JSON response as a failed request.
  }

  const hostname = body?.hostname || 'unknown';
  backendRequests.add(1, { backend: hostname });

  check(response, {
    'Load Balancer returns HTTP 200': (res) => res.status === 200,
    'Response contains backend hostname': () => Boolean(body?.hostname),
  });

  if (response.status !== 200) {
    fail(`Request failed with HTTP ${response.status}: ${response.body}`);
  }
}
