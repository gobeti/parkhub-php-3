Apply these instructions when working in `parkhub-web/**` or `resources/js/**`.

- Prioritize correctness, accessibility, and browser security over cosmetic churn.
- Treat rendered content and URL-derived state as untrusted until proven otherwise.
- Flag missing loading, empty, and error states.
- Call out unsafe token handling, client-side trust decisions, and repeated network requests.
- Recommend regression tests for critical user journeys and admin flows.
