import http from 'k6/http';
import { check, sleep } from 'k6';

export const options = {
  stages: [
    { duration: '30s', target: 20 },
    { duration: '1m', target: 20 },
    { duration: '10s', target: 0 },
  ],
};

export default function () {
    // Replace with your actual token after Sign in with admin account
  let res = http.get('http://backend/api/v1/orders/admin', {
    headers: {
      'Authorization': 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL3YxL3VzZXJzL3NpZ24taW4iLCJpYXQiOjE3OTQyMzQwNzYsImV4cCI6MTc5NDIzNDk3NiwibmJmIjoxNzk0MjM0MDc2LCJqdGkiOiIzRzN5cGE5WVM3OU12WlVsIiwic3ViIjoiMSIsInBydiI6ImJiNjVkOWI4ZmJmMGRhOTgyN2M4ZWQyMzFkOWM1NGM4MTdmMGZiYjIifQ.fTh2qRayaqpC1W4vHJg74jUuNfBaot7DuLorWBlDwJQ',
    },
  });

  check(res, {
    'status is 200': (r) => {
      if (r.status !== 200) {
        console.log(`Failed with status ${r.status}: ${r.body}`);
      }
      return r.status === 200;
    },
  });

  sleep(1);
}
