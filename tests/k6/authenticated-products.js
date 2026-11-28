import http from "k6/http";
import { check, fail } from "k6";

const baseUrl = "http://loadbalancer/api/v1";
const marker = __ENV.K6_USER_AGENT || "k6-auth-products";

export const options = {
  scenarios: {
    authenticated_product_reads: {
      executor: "shared-iterations",
      vus: 20,
      iterations: 500,
      maxDuration: "3m",
    },
  },
  thresholds: {
    checks: ["rate==1"],
    http_req_failed: ["rate<0.01"],
    http_req_duration: ["p(95)<3000"],
  },
};

export function setup() {
  const response = http.post(
    `${baseUrl}/users/sign-in`,
    JSON.stringify({
      email: __ENV.K6_EMAIL,
      password: __ENV.K6_PASSWORD,
    }),
    { headers: { "Content-Type": "application/json", Accept: "application/json" } }
  );

  if (response.status !== 200) {
    fail(`Login failed with HTTP ${response.status}; do not share credentials.`);
  }

  const token = response.json()?.data?.token;
  if (!token) {
    fail("Login response did not contain data.token.");
  }

  return { token };
}

export default function (data) {
  const response = http.get(
    `${baseUrl}/products?page=1&per_page=4`,
    {
      headers: {
        Accept: "application/json",
        Authorization: `Bearer ${data.token}`,
        "User-Agent": marker,
      },
      tags: { test_name: "authenticated_product_reads" },
    }
  );

  check(response, {
    "authenticated products request returns 200": (r) => r.status === 200,
  });

  if (response.status !== 200) {
    fail(`Products request failed with HTTP ${response.status}.`);
  }
}
