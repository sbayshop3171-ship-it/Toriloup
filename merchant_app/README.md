# Toriloup Merchant Android app

This Flutter app opens the merchant workspace at `https://merchant.toriloup.com/dashboard` in an Android WebView. The website handles login and sends signed-out users to its login page.

## App details

- Application ID: `com.toriloup.merchant`
- Display name: Toriloup Merchant
- Android minimum SDK: 24
- Android target SDK: 36
- Launcher icon source: `logo app icon/T.png`

## Local development

Install Flutter, Java 17, and Android SDK 36, then run:

```sh
flutter pub get
flutter run
```

The app keeps merchant-domain pages in the WebView, opens off-domain HTTPS and contact links in another app, handles Android back navigation, displays a loading/retry screen, and opens Android's file picker for website file inputs.

## Play release status

The debug app can be installed locally. Do not upload a debug-signed or unsigned build to Play Console. A Play release still needs the private upload signing key configured outside source control, a signed AAB, and release verification. A public `.pem` upload certificate by itself cannot sign an AAB; it must correspond to the private upload key registered in Play Console.

Firebase Cloud Messaging is not configured yet. Privacy/account deletion, Data safety, app access for reviewers, subscription payment policy, store listing assets, closed testing, and end-to-end checks on supported Android versions also need completion before public release.
# Toriloup Merchant Android app

The Android app opens `https://merchant.toriloup.com/dashboard` in a WebView and uses the existing merchant sign-in and tenant context. Its launcher icon is generated from `logo app icon/T.png`.

## Firebase Android configuration

The Owner configures the shared Firebase project in the Owner dashboard. The service-account JSON belongs only on the Laravel server and is stored encrypted there. Never copy that JSON into this Flutter project.

The Android app's Firebase client configuration is saved at `android/app/google-services.json` for this registered package: `com.toriloup.merchant`. The Flutter app currently initializes Firebase from build-time `--dart-define` values, so keep passing the public client values below when building; merely adding the JSON file does not inject those Dart values.

For an Android build, pass the Firebase **public client** values from the registered Android app whose package name is `com.toriloup.merchant`:

```sh
flutter build appbundle \
  --dart-define=FIREBASE_API_KEY=YOUR_ANDROID_API_KEY \
  --dart-define=FIREBASE_APP_ID=YOUR_ANDROID_APP_ID \
  --dart-define=FIREBASE_MESSAGING_SENDER_ID=YOUR_SENDER_ID \
  --dart-define=FIREBASE_PROJECT_ID=YOUR_PROJECT_ID \
  --dart-define=FIREBASE_STORAGE_BUCKET=YOUR_STORAGE_BUCKET
```

These values are public Firebase app configuration, not a service-account key. If they are not supplied yet, the app still opens the merchant website, but native push is not initialized. The website sends its FCM token to the authenticated API only after the merchant signs in. Android WebView browser push registration is skipped so the app registers one native token per installation.

The app requests notification permission after the signed-in merchant dashboard asks for a token. It displays foreground alerts in-app, lets Android display background alerts, and routes notification taps back to the target merchant page.
