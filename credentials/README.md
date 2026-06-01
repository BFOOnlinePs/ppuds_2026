# Firebase Credentials

Place the Firebase service account file at:

```text
credentials/firebase-service-account.json
```

This file contains secrets and is ignored by Git.

The application reads it through:

```text
FIREBASE_CREDENTIALS=credentials/firebase-service-account.json
```
