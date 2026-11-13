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
      'Authorization': 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vbG9jYWxob3N0L2FwaS92MS91c2Vycy9zaWduLWluIiwiaWF0IjoxNzk0NDczODQwLCJleHAiOjE3OTQ0NzQ3NDAsIm5iZiI6MTc5NDQ3Mzg0MCwianRpIjoicm9TUER2Qzd1N2c5REhYMSIsInN1YiI6IjEiLCJwcnYiOiJiYjY1ZDliOGZiZjBkYTk4MjdjOGVkMjMxZDljNTRjODE3ZjBmYmIyIn0.PjFB5mY_ODnFtkG-5ZqpI6gd5H4uK9wtT0iYVBc9ppw',
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
