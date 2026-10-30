import 'dart:convert';
import 'dart:async';

import 'package:file_picker/file_picker.dart';
import 'package:flutter/foundation.dart';
import 'package:flutter/material.dart';
import 'package:flutter/services.dart';
import 'package:firebase_core/firebase_core.dart';
import 'package:firebase_messaging/firebase_messaging.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:uuid/uuid.dart';
import 'package:url_launcher/url_launcher.dart';
import 'package:webview_flutter/webview_flutter.dart';
import 'package:webview_flutter_android/webview_flutter_android.dart';

const _merchantHost = 'merchant.toriloup.com';
final _merchantHome = Uri.https(_merchantHost, '/dashboard');
const _brandColor = Color(0xFFF23E14);
const _firebaseApiKey = String.fromEnvironment('FIREBASE_API_KEY');
const _firebaseAppId = String.fromEnvironment('FIREBASE_APP_ID');
const _firebaseSenderId = String.fromEnvironment('FIREBASE_MESSAGING_SENDER_ID');
const _firebaseProjectId = String.fromEnvironment('FIREBASE_PROJECT_ID');
const _firebaseStorageBucket = String.fromEnvironment('FIREBASE_STORAGE_BUCKET');
final _messengerKey = GlobalKey<ScaffoldMessengerState>();

bool get _hasFirebaseConfig =>
    _firebaseApiKey.isNotEmpty &&
    _firebaseAppId.isNotEmpty &&
    _firebaseSenderId.isNotEmpty &&
    _firebaseProjectId.isNotEmpty;

FirebaseOptions get _firebaseOptions => FirebaseOptions(
      apiKey: _firebaseApiKey,
      appId: _firebaseAppId,
      messagingSenderId: _firebaseSenderId,
      projectId: _firebaseProjectId,
      storageBucket: _firebaseStorageBucket.isEmpty ? null : _firebaseStorageBucket,
    );

Future<void> main() async {
  WidgetsFlutterBinding.ensureInitialized();
  SystemChrome.setSystemUIOverlayStyle(const SystemUiOverlayStyle(
    statusBarColor: Colors.white,
    statusBarIconBrightness: Brightness.dark,
    systemNavigationBarColor: Colors.white,
    systemNavigationBarIconBrightness: Brightness.dark,
  ));
  if (_hasFirebaseConfig) {
    try {
      await Firebase.initializeApp(options: _firebaseOptions);
    } catch (_) {
      // Firebase setup is optional until the Owner configures the project.
      // The merchant WebView must remain usable if push is not configured.
    }
  }
  runApp(const ToriloupMerchantApp());
}

class ToriloupMerchantApp extends StatelessWidget {
  const ToriloupMerchantApp({super.key});

  @override
  Widget build(BuildContext context) {
    return MaterialApp(
      scaffoldMessengerKey: _messengerKey,
      title: 'Toriloup Merchant',
      debugShowCheckedModeBanner: false,
      theme: ThemeData(
        colorScheme: ColorScheme.fromSeed(seedColor: _brandColor),
        scaffoldBackgroundColor: Colors.white,
        useMaterial3: true,
      ),
      home: const MerchantWebViewScreen(),
    );
  }
}

class MerchantWebViewScreen extends StatefulWidget {
  const MerchantWebViewScreen({super.key});

  @override
  State<MerchantWebViewScreen> createState() => _MerchantWebViewScreenState();
}

class _MerchantWebViewScreenState extends State<MerchantWebViewScreen> {
  late final WebViewController _controller;
  SharedPreferences? _preferences;
  FirebaseMessaging? _messaging;
  StreamSubscription<String>? _tokenRefreshSubscription;
  StreamSubscription<RemoteMessage>? _messageSubscription;
  StreamSubscription<RemoteMessage>? _messageOpenedSubscription;
  String? _installationId;
  Map<String, dynamic>? _pendingPushOpen;
  int _progress = 0;
  bool _hasLoadedPage = false;
  String? _loadError;
  DateTime? _lastBackPressedAt;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..addJavaScriptChannel(
        'ToriloupNativePush',
        onMessageReceived: (message) => unawaited(_handleNativeBridgeMessage(message.message)),
      );
    unawaited(_initializeWebView());
  }

  Future<void> _initializeWebView() async {
    try {
      await _initializePush();
      await _controller.setJavaScriptMode(JavaScriptMode.unrestricted);
      await _controller.setBackgroundColor(Colors.white);
      await _controller.setNavigationDelegate(NavigationDelegate(
        onProgress: (progress) {
          if (mounted) setState(() => _progress = progress);
        },
        onPageStarted: (_) {
          if (!mounted) return;
          setState(() {
            _loadError = null;
            _progress = 0;
          });
        },
        onPageFinished: (_) {
          if (!mounted) return;
          setState(() {
            _hasLoadedPage = true;
            _progress = 100;
            _loadError = null;
          });
          _flushPendingPushOpen();
        },
        onWebResourceError: (error) {
          if (error.isForMainFrame != true || !mounted) return;
          setState(() => _loadError = 'Could not load the merchant workspace.');
        },
        onNavigationRequest: _handleNavigationRequest,
      ));

      if (_controller.platform is AndroidWebViewController) {
        final androidController =
            _controller.platform as AndroidWebViewController;
        await AndroidWebViewController.enableDebugging(kDebugMode);
        await androidController.setOnShowFileSelector(_selectWebFiles);
      }

      await _controller.loadRequest(_merchantHome);
    } catch (_) {
      if (mounted) {
        setState(() => _loadError = 'Could not start the merchant workspace.');
      }
    }
  }

  Future<void> _initializePush() async {
    if (!_hasFirebaseConfig || Firebase.apps.isEmpty) return;

    try {
      _preferences = await SharedPreferences.getInstance();
      _installationId = _preferences!.getString('push_installation_id');
      if (_installationId == null || _installationId!.isEmpty) {
        _installationId = const Uuid().v4();
        await _preferences!.setString('push_installation_id', _installationId!);
      }

      _messaging = FirebaseMessaging.instance;
      _messageSubscription = FirebaseMessaging.onMessage.listen(_showForegroundPush);
      _messageOpenedSubscription = FirebaseMessaging.onMessageOpenedApp.listen(_openPushTarget);
      // The Laravel FCM HTTP v1 sender requires the registration token, not
      // the newer Firebase Installation ID emitted by onRegistered.
      // ignore: deprecated_member_use
      _tokenRefreshSubscription = _messaging!.onTokenRefresh.listen((token) {
        _dispatchToken(token);
      });

      final initialMessage = await _messaging!.getInitialMessage();
      if (initialMessage != null) {
        _pendingPushOpen = _pushDetail(initialMessage);
      }
    } catch (_) {
      _messaging = null;
    }
  }

  Future<void> _handleNativeBridgeMessage(String rawMessage) async {
    if (!_hasFirebaseConfig || _messaging == null) return;

    try {
      final message = jsonDecode(rawMessage);
      if (message is! Map || message['action'] != 'getToken') return;

      final permission = await _messaging!.requestPermission(
        alert: true,
        badge: true,
        sound: true,
      );
      if (permission.authorizationStatus != AuthorizationStatus.authorized &&
          permission.authorizationStatus != AuthorizationStatus.provisional) {
        return;
      }

      // Keep sending the FCM registration token expected by the server API.
      // ignore: deprecated_member_use
      final token = await _messaging!.getToken();
      if (token != null && token.isNotEmpty) _dispatchToken(token);
    } catch (_) {
      // Push permission and token failures should not block the dashboard.
    }
  }

  void _dispatchToken(String token) {
    final installationId = _installationId;
    if (installationId == null || !_hasLoadedPage) return;
    _dispatchPageEvent('toriloup:push-token', {
      'token': token,
      'installation_id': installationId,
      'platform': 'android',
    });
  }

  void _showForegroundPush(RemoteMessage message) {
    final title = message.notification?.title ?? message.data['title']?.toString() ?? 'Toriloup Merchant';
    final body = message.notification?.body ?? message.data['body']?.toString() ?? '';
    _messengerKey.currentState
      ?..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(
        content: Text(body.isEmpty ? title : '$title\n$body', maxLines: 3, overflow: TextOverflow.ellipsis),
        action: SnackBarAction(
          label: 'Open',
          onPressed: () => _openPushTarget(message),
        ),
        duration: const Duration(seconds: 6),
      ));
  }

  Map<String, dynamic> _pushDetail(RemoteMessage message) => {
        ...message.data,
        if (message.notification?.title != null) 'title': message.notification!.title,
        if (message.notification?.body != null) 'body': message.notification!.body,
      };

  void _openPushTarget(RemoteMessage message) {
    final detail = _pushDetail(message);
    if (!_hasLoadedPage) {
      _pendingPushOpen = detail;
      return;
    }
    _dispatchPageEvent('toriloup:push-open', detail);
  }

  void _flushPendingPushOpen() {
    final pending = _pendingPushOpen;
    if (pending == null) return;
    _pendingPushOpen = null;
    _dispatchPageEvent('toriloup:push-open', pending);
  }

  void _dispatchPageEvent(String eventName, Map<String, dynamic> detail) {
    if (!_hasLoadedPage) return;
    final encoded = jsonEncode(detail);
    unawaited(_controller.runJavaScript(
      "window.dispatchEvent(new CustomEvent('$eventName', { detail: $encoded }));",
    ));
  }

  NavigationDecision _handleNavigationRequest(NavigationRequest request) {
    if (!request.isMainFrame) return NavigationDecision.navigate;

    final uri = Uri.tryParse(request.url);
    if (uri == null) return NavigationDecision.prevent;

    if (uri.scheme == 'https' && uri.host.toLowerCase() == _merchantHost) {
      return NavigationDecision.navigate;
    }

    if (uri.scheme == 'https' ||
        uri.scheme == 'tel' ||
        uri.scheme == 'mailto' ||
        uri.scheme == 'sms') {
      unawaited(_openExternal(uri));
    }

    return NavigationDecision.prevent;
  }

  Future<void> _openExternal(Uri uri) async {
    try {
      await launchUrl(uri, mode: LaunchMode.externalApplication);
    } catch (_) {
      if (mounted) _showMessage('This link could not be opened.');
    }
  }

  Future<List<String>> _selectWebFiles(FileSelectorParams params) async {
    try {
      final type = _fileTypeFor(params.acceptTypes);
      final files = params.mode == FileSelectorMode.openMultiple
          ? await FilePicker.pickFiles(type: type)
          : await FilePicker.pickFile(type: type).then(
              (file) => file == null ? <PlatformFile>[] : <PlatformFile>[file],
            );

      return files.map((file) => file.path).whereType<String>().toList();
    } catch (_) {
      if (mounted) _showMessage('File selection is unavailable.');
      return <String>[];
    }
  }

  FileType _fileTypeFor(List<String> acceptTypes) {
    final types = acceptTypes
        .map((type) => type.trim().toLowerCase())
        .where((type) => type.isNotEmpty)
        .toList();
    if (types.isEmpty) return FileType.any;
    if (types.every((type) => type.startsWith('image/'))) return FileType.image;
    if (types.every((type) => type.startsWith('video/'))) return FileType.video;
    if (types.every((type) => type.startsWith('audio/'))) return FileType.audio;
    return FileType.any;
  }

  Future<void> _handleSystemBack() async {
    if (await _controller.canGoBack()) {
      await _controller.goBack();
      _lastBackPressedAt = null;
      return;
    }

    final now = DateTime.now();
    final lastPressed = _lastBackPressedAt;
    if (lastPressed != null && now.difference(lastPressed).inSeconds < 2) {
      await SystemNavigator.pop();
      return;
    }

    _lastBackPressedAt = now;
    if (mounted) _showMessage('Press back again to close the app.');
  }

  void _showMessage(String message) {
    ScaffoldMessenger.of(context)
      ..hideCurrentSnackBar()
      ..showSnackBar(SnackBar(content: Text(message)));
  }

  @override
  void dispose() {
    unawaited(_tokenRefreshSubscription?.cancel());
    unawaited(_messageSubscription?.cancel());
    unawaited(_messageOpenedSubscription?.cancel());
    super.dispose();
  }

  Future<void> _retry() async {
    setState(() {
      _loadError = null;
      _progress = 0;
    });
    await _controller.loadRequest(_merchantHome);
  }

  @override
  Widget build(BuildContext context) {
    return PopScope<Object?>(
      canPop: false,
      onPopInvokedWithResult: (didPop, _) {
        if (!didPop) unawaited(_handleSystemBack());
      },
      child: Scaffold(
        body: SafeArea(
          child: Stack(
            fit: StackFit.expand,
            children: [
              WebViewWidget(controller: _controller),
              if (_progress < 100 && _loadError == null)
                Positioned(
                  top: 0,
                  left: 0,
                  right: 0,
                  child: LinearProgressIndicator(
                    value: _progress == 0 ? null : _progress / 100,
                    minHeight: 2,
                    color: _brandColor,
                    backgroundColor: Colors.white,
                  ),
                ),
              if (!_hasLoadedPage && _loadError == null)
                const _StartupSplash(),
              if (_loadError != null) _LoadErrorPanel(onRetry: _retry),
            ],
          ),
        ),
      ),
    );
  }
}

class _StartupSplash extends StatelessWidget {
  const _StartupSplash();

  @override
  Widget build(BuildContext context) {
    return const ColoredBox(
      color: Colors.white,
      child: Center(
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Image(image: AssetImage('logo app icon/T.png'), width: 88, height: 88),
            SizedBox(height: 22),
            SizedBox(
              width: 30,
              height: 30,
              child: CircularProgressIndicator(
                strokeWidth: 3,
                color: _brandColor,
              ),
            ),
          ],
        ),
      ),
    );
  }
}

class _LoadErrorPanel extends StatelessWidget {
  const _LoadErrorPanel({required this.onRetry});

  final Future<void> Function() onRetry;

  @override
  Widget build(BuildContext context) {
    return ColoredBox(
      color: Colors.white,
      child: Center(
        child: Padding(
          padding: const EdgeInsets.all(28),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Image(
                image: AssetImage('logo app icon/T.png'),
                width: 76,
                height: 76,
              ),
              const SizedBox(height: 20),
              const Text(
                'Can’t connect right now',
                style: TextStyle(fontSize: 20, fontWeight: FontWeight.w700),
              ),
              const SizedBox(height: 8),
              const Text(
                'Check your internet connection and try again.',
                textAlign: TextAlign.center,
                style: TextStyle(color: Color(0xFF667085)),
              ),
              const SizedBox(height: 20),
              FilledButton.icon(
                onPressed: () => unawaited(onRetry()),
                icon: const Icon(Icons.refresh),
                label: const Text('Try again'),
                style: FilledButton.styleFrom(backgroundColor: _brandColor),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
