import http from 'k6/http';
import { check, fail } from 'k6';
import { Trend } from 'k6/metrics';

const baseUrl = (__ENV.API_BASE_URL || 'http://backend').replace(/\/+$/, '');
const email = __ENV.TEST_EMAIL;
const password = __ENV.TEST_PASSWORD;
const productId = Number(__ENV.PRODUCT_ID);
const iterations = Number(__ENV.TEST_ITERATIONS || 1);
const vus = Number(__ENV.TEST_VUS || 1);

const writeDuration = new Trend('order_write_duration');
const immediateReadDuration = new Trend('order_immediate_read_duration');

export const options = {
  scenarios: {
    read_write_verification: {
      executor: 'shared-iterations',
      vus,
      iterations,
      maxDuration: '2m',
    },
  },
  thresholds: {
    checks: ['rate==1'],
  },
};

export function setup() {
  if (!email || !password) {
    fail('Set TEST_EMAIL and TEST_PASSWORD to an authorized admin account.');
  }

  if (!Number.isInteger(productId) || productId <= 0) {
    fail('Set PRODUCT_ID to an existing product with available inventory.');
  }

  if (!Number.isInteger(iterations) || iterations < 1 || !Number.isInteger(vus) || vus < 1) {
    fail('TEST_ITERATIONS and TEST_VUS must be positive integers.');
  }

  const response = http.post(
    `${baseUrl}/api/v1/users/sign-in`,
    JSON.stringify({ email, password }),
    { headers: { 'Content-Type': 'application/json', Accept: 'application/json' } },
  );

  let body;
  try {
    body = response.json();
  } catch {
    fail(`Sign-in returned non-JSON response (HTTP ${response.status}).`);
  }

  if (response.status !== 200 || body?.success !== true || !body?.data?.token) {
    fail(`Sign-in failed (HTTP ${response.status}); check the test account and API response.`);
  }

  return { token: body.data.token };
}

export default function ({ token }) {
  const params = {
    headers: {
      Authorization: `Bearer ${token}`,
      'Content-Type': 'application/json',
      Accept: 'application/json',
    },
  };
  const writeResponse = http.post(
    `${baseUrl}/api/v1/orders`,
    JSON.stringify({ items: [{ product_id: productId, quantity: 1 }] }),
    params,
  );

  writeDuration.add(writeResponse.timings.duration);

  let createdOrder;
  try {
    createdOrder = writeResponse.json();
  } catch {
    check(writeResponse, { 'POST order returns HTTP 201 JSON': () => false });
    return;
  }

  const writeSucceeded = check(writeResponse, {
    'POST order is created successfully (HTTP 201)': (response) =>
      response.status === 201 && createdOrder?.success === true,
    'POST response contains the created order ID': () =>
      createdOrder?.data?.id !== undefined && createdOrder?.data?.id !== null,
  });

  if (!writeSucceeded || !createdOrder?.data?.id) {
    console.error(`Order creation failed (HTTP ${writeResponse.status}).`);
    return;
  }

  const orderId = String(createdOrder.data.id);
  const readResponse = http.get(
    `${baseUrl}/api/v1/orders/admin`,
    params,
  );

  immediateReadDuration.add(readResponse.timings.duration);

  let readBody;
  try {
    readBody = readResponse.json();
  } catch {
    check(readResponse, {
      'immediate GET returns the new order': () => false,
    });
    return;
  }

  const visibleImmediately = check(readResponse, {
    'immediate GET sees the new order (no replica lag)': (response) =>
      response.status === 200 &&
      readBody?.success === true &&
      Array.isArray(readBody?.data?.data) &&
      readBody.data.data.some((order) => String(order.id) === orderId),
  });

  if (!visibleImmediately) {
    console.error(
      `Order ${orderId} was not immediately visible after POST (GET HTTP ${readResponse.status}); ` +
      'this can indicate replica lag or that sticky does not span separate HTTP requests.',
    );
  }
}
