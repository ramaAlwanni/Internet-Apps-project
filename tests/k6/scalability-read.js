import http from "k6/http";
import { check } from "k6";

export const options = {
    discardResponseBodies: true,
    scenarios: {
        concurrent_read_test: {
            executor: "shared-iterations",
            vus: 20,
            iterations: 500,
            maxDuration: "3m",
        },
    },
    thresholds: {
        http_req_failed: ["rate<0.01"],
        http_req_duration: ["p(95)<3000"],
    },
};

export default function () {
    const response = http.get("http://loadbalancer/", {
        headers: {
            "User-Agent": "k6-final-20261125-0147",
        },
        tags: { test_name: "scalability_read" },
    });

    check(response, {
        "status is 200": (r) => r.status === 200,
    });
}
