// PPUDS — internal sign-in API client for Flutter.
//
// Drop this file into the app (e.g. lib/features/auth/data/ppuds_auth_api.dart)
// and pass it the app's existing Dio instance. Requires Dart 3 (sealed classes
// and patterns) and the `dio` package.
//
// Covers every internal sign-in endpoint:
//   GET  /api/v1/auth/options        how the app signs in (also data.auth in /api/v1/config)
//   POST /api/v1/auth/identify       how ONE account signs in (when options.perUser)
//   POST /api/v1/auth/login          email + password (may ask for a code: two-step)
//   POST /api/v1/auth/otp/request    send / resend a one-time code
//   POST /api/v1/auth/otp/verify     exchange the code for a token
//   POST /api/v1/auth/qr/scan        any scanned QR: personal → signed in, company → continue
//   POST /api/v1/auth/logout         sign out (Bearer)
//
// Every response is wrapped as { status, message, data }. `message` is already
// translated by the server (send Accept-Language) and can be shown as is.
//
// Full explanation: docs/mobile-authentication-api.md

import 'package:dio/dio.dart';

// ---------------------------------------------------------------------------
// Enums
// ---------------------------------------------------------------------------

/// How someone signs in, as chosen in the dashboard.
enum LoginMode {
  /// Email + password, no code.
  password,

  /// Email, then the one-time code sent to them. No password.
  code,

  /// The user picks: password, or a code.
  passwordOrCode,

  /// Email + password, then a one-time code (two-step).
  passwordThenCode;

  static LoginMode fromJson(String? value) => switch (value) {
        'code' => LoginMode.code,
        'password_or_code' => LoginMode.passwordOrCode,
        'password_then_code' => LoginMode.passwordThenCode,
        _ => LoginMode.password,
      };
}

/// Where a one-time code is sent.
enum CodeChannel {
  mail,
  sms;

  static CodeChannel? fromJson(String? value) => switch (value) {
        'mail' => CodeChannel.mail,
        'sms' => CodeChannel.sms,
        _ => null,
      };

  String toJson() => name; // 'mail' | 'sms'
}

// ---------------------------------------------------------------------------
// Models
// ---------------------------------------------------------------------------

/// What the sign-in screen should offer.
///
/// From [PpudsAuthApi.options] (everyone) or [PpudsAuthApi.identify] (one
/// account). For `identify`, the QR fields and [perUser] are always false.
class AuthOptions {
  /// The method in force. From `options`: the general one; from `identify`: that account's.
  final LoginMode mode;

  /// True when people or roles have their own method: ask for the email first,
  /// then call [PpudsAuthApi.identify] and build the screen from its answer.
  final bool perUser;

  /// Show the password option.
  final bool passwordLogin;

  /// Show "sign in with a code" (email only, then code).
  final bool codeLogin;

  /// A correct password may be followed by a code.
  final bool twoStep;

  /// The company's printed QR (then email + code) is on.
  final bool companyQrLogin;

  /// Personal QR codes (scan → signed in straight away) are on.
  final bool personalQrLogin;

  final List<CodeChannel> channels;
  final CodeChannel? defaultChannel;

  /// Let the user choose mail / sms (send it as `channel`).
  final bool channelChoice;

  /// Number of digits of the code; null when no code is involved.
  final int? codeLength;

  const AuthOptions({
    required this.mode,
    required this.perUser,
    required this.passwordLogin,
    required this.codeLogin,
    required this.twoStep,
    required this.companyQrLogin,
    required this.personalQrLogin,
    required this.channels,
    required this.defaultChannel,
    required this.channelChoice,
    required this.codeLength,
  });

  /// Show a "Scan QR code" button.
  bool get showScanQr => companyQrLogin || personalQrLogin;

  factory AuthOptions.fromJson(Map<String, dynamic> json) => AuthOptions(
        mode: LoginMode.fromJson(json['mode'] as String?),
        perUser: json['per_user'] == true,
        passwordLogin: json['password_login'] == true,
        codeLogin: json['code_login'] == true,
        twoStep: json['two_step'] == true,
        companyQrLogin: json['company_qr_login'] == true,
        personalQrLogin: json['personal_qr_login'] == true,
        channels: ((json['channels'] as List?) ?? const [])
            .map((e) => CodeChannel.fromJson(e as String?))
            .whereType<CodeChannel>()
            .toList(),
        defaultChannel: CodeChannel.fromJson(json['default_channel'] as String?),
        channelChoice: json['channel_choice'] == true,
        codeLength: json['code_length'] as int?,
      );
}

/// A code is on its way (or would be — see the note on [PpudsAuthApi.requestCode]).
class CodeChallenge {
  final CodeChannel channel;

  /// Seconds the code stays valid.
  final int expiresIn;

  /// Seconds before "resend" may be pressed (drive a countdown with it).
  final int resendAfter;

  final int codeLength;

  /// Two-step only: keep it in memory and send it back with the code / to resend.
  final String? loginTicket;

  const CodeChallenge({
    required this.channel,
    required this.expiresIn,
    required this.resendAfter,
    required this.codeLength,
    this.loginTicket,
  });

  factory CodeChallenge.fromJson(Map<String, dynamic> json) => CodeChallenge(
        channel: CodeChannel.fromJson(json['channel'] as String?) ?? CodeChannel.mail,
        expiresIn: json['expires_in'] as int,
        resendAfter: json['resend_after'] as int,
        codeLength: json['code_length'] as int,
        loginTicket: json['login_ticket'] as String?,
      );
}

/// A successful sign-in: the Sanctum token and the user, exactly as the
/// existing /api/v1/auth/login has always returned them.
class AuthSession {
  final String token;
  final String tokenType; // "Bearer"

  /// The user object (UserResource): id, name, email, phone, image, roles, …
  /// Map it with the app's existing user model.
  final Map<String, dynamic> user;

  const AuthSession({required this.token, required this.tokenType, required this.user});

  factory AuthSession.fromJson(Map<String, dynamic> json) => AuthSession(
        token: json['token'] as String,
        tokenType: (json['token_type'] as String?) ?? 'Bearer',
        user: Map<String, dynamic>.from(json['user'] as Map),
      );
}

/// The company behind a scanned printed QR code.
class QrCompany {
  final int id;
  final String name;
  final String? logoUrl;

  const QrCompany({required this.id, required this.name, this.logoUrl});

  factory QrCompany.fromJson(Map<String, dynamic> json) => QrCompany(
        id: json['id'] as int,
        name: (json['name'] as String?) ?? '',
        logoUrl: json['logo_url'] as String?,
      );
}

// ---------------------------------------------------------------------------
// Results
// ---------------------------------------------------------------------------

/// Answer of [PpudsAuthApi.loginWithPassword].
sealed class PasswordLoginResult {
  const PasswordLoginResult();
}

/// Signed in.
final class PasswordSignedIn extends PasswordLoginResult {
  final AuthSession session;
  const PasswordSignedIn(this.session);
}

/// Two-step: the password was right; now ask for the code
/// (verify with [CodeChallenge.loginTicket]).
final class PasswordNeedsCode extends PasswordLoginResult {
  final CodeChallenge challenge;
  const PasswordNeedsCode(this.challenge);
}

/// Answer of [PpudsAuthApi.scanQr].
sealed class QrScanResult {
  const QrScanResult();
}

/// A personal QR code: the holder is signed in already — no email, no code.
final class QrSignedIn extends QrScanResult {
  final AuthSession session;
  const QrSignedIn(this.session);
}

/// The company's printed QR code: show the company, ask for the email, then
/// the code — sending the same [qrToken] to requestCode and verifyCode.
final class QrNeedsCode extends QrScanResult {
  final QrCompany company;
  final String qrToken;
  const QrNeedsCode(this.company, this.qrToken);
}

// ---------------------------------------------------------------------------
// Errors
// ---------------------------------------------------------------------------

/// Any non-2xx answer, already parsed.
///
/// | status | meaning                                                       | what to do                                   |
/// |--------|---------------------------------------------------------------|----------------------------------------------|
/// | 401    | wrong credentials, or login_ticket expired / invalid          | show message; with a ticket: back to password |
/// | 403    | this way of signing in is switched off, or this account signs | show message; reload [PpudsAuthApi.options]   |
/// |        | in with a code only (after a correct password)                |                                              |
/// | 404    | QR code invalid / revoked / expired, or no longer usable      | show message; scan the current code           |
/// | 422    | validation error, or the code is wrong / used / expired       | show message ([fieldErrors] per field)        |
/// | 429    | too many requests                                             | wait [retryAfter] seconds                     |
class PpudsApiException implements Exception {
  final int? statusCode;

  /// Ready to show to the user (translated by the server).
  final String message;

  /// 429 only: seconds to wait.
  final int? retryAfter;

  /// 422 only: field → messages.
  final Map<String, List<String>> fieldErrors;

  const PpudsApiException({
    required this.statusCode,
    required this.message,
    this.retryAfter,
    this.fieldErrors = const {},
  });

  bool get isNetworkError => statusCode == null;
  bool get isUnauthorized => statusCode == 401;
  bool get isForbidden => statusCode == 403;
  bool get isNotFound => statusCode == 404;
  bool get isValidation => statusCode == 422;
  bool get isThrottled => statusCode == 429;

  factory PpudsApiException.fromDio(DioException e) {
    final response = e.response;
    final body = response?.data;

    if (body is! Map) {
      return PpudsApiException(
        statusCode: response?.statusCode,
        message: 'Something went wrong. Please try again.',
      );
    }

    final data = body['data'];
    int? retryAfter;
    var fieldErrors = <String, List<String>>{};

    if (data is Map) {
      retryAfter = data['retry_after'] is int ? data['retry_after'] as int : null;

      if (response?.statusCode == 422) {
        fieldErrors = {
          for (final entry in data.entries)
            if (entry.value is List) entry.key.toString(): List<String>.from(entry.value as List),
        };
      }
    }

    retryAfter ??= int.tryParse(response?.headers.value('retry-after') ?? '');

    return PpudsApiException(
      statusCode: response?.statusCode,
      message: (body['message'] as String?) ?? 'Something went wrong. Please try again.',
      retryAfter: retryAfter,
      fieldErrors: fieldErrors,
    );
  }

  @override
  String toString() => 'PpudsApiException($statusCode): $message';
}

// ---------------------------------------------------------------------------
// API
// ---------------------------------------------------------------------------

class PpudsAuthApi {
  PpudsAuthApi(this._dio);

  /// The app's Dio. It must have the server's baseUrl and these headers:
  ///   Accept: application/json, Accept-Language: ar | en
  /// (Authorization: Bearer <token> only after sign-in, for [logout].)
  final Dio _dio;

  /// How the app signs in. Call it every time the sign-in screen opens —
  /// the dashboard may change it at any moment.
  Future<AuthOptions> options() async {
    final data = await _get('/api/v1/auth/options');
    return AuthOptions.fromJson(data);
  }

  /// How ONE account signs in. Use when [AuthOptions.perUser] is true: ask for
  /// the email first, then build the screen from this.
  ///
  /// An unknown email answers like an ordinary account — never tell the user
  /// "account not found".
  Future<AuthOptions> identify(String email) async {
    final data = await _post('/api/v1/auth/identify', {'email': email.trim()});
    return AuthOptions.fromJson(data);
  }

  /// Email + password.
  ///
  /// Returns [PasswordSignedIn], or [PasswordNeedsCode] for a two-step account.
  /// Throws 401 for wrong credentials, and 403 when the account signs in with
  /// a code only (the message says so).
  Future<PasswordLoginResult> loginWithPassword({
    required String email,
    required String password,
    CodeChannel? channel,
    String? deviceName,
    String? fcmToken,
  }) async {
    final data = await _post('/api/v1/auth/login', {
      'email': email.trim(),
      'password': password,
      if (channel != null) 'channel': channel.toJson(),
      if (deviceName != null) 'device_name': deviceName,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });

    if (data['requires_code'] == true) {
      return PasswordNeedsCode(CodeChallenge.fromJson(data));
    }

    return PasswordSignedIn(AuthSession.fromJson(data));
  }

  /// Sends a one-time code. Exactly one of:
  ///  - [email]                 code-only sign-in;
  ///  - [email] + [qrToken]     after scanning the company's printed QR;
  ///  - [loginTicket]           resend the code of a two-step sign-in.
  ///
  /// For [email] the answer is the same whether or not the account exists, so
  /// word the UI as "If this account can sign in this way, a code was sent"
  /// (the server's [message] already says that).
  Future<CodeChallenge> requestCode({
    String? email,
    String? qrToken,
    String? loginTicket,
    CodeChannel? channel,
  }) async {
    assert(email != null || loginTicket != null, 'Pass an email or a login ticket.');

    final data = await _post('/api/v1/auth/otp/request', {
      if (email != null) 'email': email.trim(),
      if (qrToken != null) 'qr_token': qrToken,
      if (loginTicket != null) 'login_ticket': loginTicket,
      if (channel != null) 'channel': channel.toJson(),
    });

    // Keep the ticket for the next resend / verify of a two-step sign-in.
    final challenge = CodeChallenge.fromJson(data);
    return loginTicket == null
        ? challenge
        : CodeChallenge(
            channel: challenge.channel,
            expiresIn: challenge.expiresIn,
            resendAfter: challenge.resendAfter,
            codeLength: challenge.codeLength,
            loginTicket: loginTicket,
          );
  }

  /// Exchanges the code for a session. Exactly one of:
  ///  - [email]                 code-only sign-in;
  ///  - [email] + [qrToken]     company printed QR;
  ///  - [loginTicket]           second step of a two-step sign-in.
  ///
  /// Throws 422 for a wrong / used / expired code, 429 after too many wrong
  /// codes, 401 when the login ticket expired (go back to the password).
  Future<AuthSession> verifyCode({
    required String code,
    String? email,
    String? qrToken,
    String? loginTicket,
    String? deviceName,
    String? fcmToken,
  }) async {
    assert(email != null || loginTicket != null, 'Pass an email or a login ticket.');

    final data = await _post('/api/v1/auth/otp/verify', {
      'code': code.trim(),
      if (email != null) 'email': email.trim(),
      if (qrToken != null) 'qr_token': qrToken,
      if (loginTicket != null) 'login_ticket': loginTicket,
      if (deviceName != null) 'device_name': deviceName,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });

    return AuthSession.fromJson(data);
  }

  /// Any QR the camera read — pass the scanned text exactly as is.
  ///
  /// A personal code signs its holder in straight away ([QrSignedIn]); the
  /// company's printed code returns the company ([QrNeedsCode]) and the app
  /// continues with email + code. Throws 404 for an invalid / revoked code.
  Future<QrScanResult> scanQr(String scannedText, {String? deviceName, String? fcmToken}) async {
    final data = await _post('/api/v1/auth/qr/scan', {
      'qr_token': scannedText,
      if (deviceName != null) 'device_name': deviceName,
      if (fcmToken != null) 'fcm_token': fcmToken,
    });

    if (data['type'] == 'personal') {
      return QrSignedIn(AuthSession.fromJson(data));
    }

    return QrNeedsCode(
      QrCompany.fromJson(Map<String, dynamic>.from(data['company'] as Map)),
      scannedText,
    );
  }

  /// Signs out on the server (the Dio must carry the Bearer token).
  Future<void> logout() async {
    await _post('/api/v1/auth/logout', const {});
  }

  // -------------------------------------------------------------------------

  Future<Map<String, dynamic>> _get(String path) => _send(() => _dio.get(path));

  Future<Map<String, dynamic>> _post(String path, Map<String, dynamic> body) =>
      _send(() => _dio.post(path, data: body));

  Future<Map<String, dynamic>> _send(Future<Response<dynamic>> Function() call) async {
    try {
      final response = await call();
      final body = response.data;
      final data = body is Map ? body['data'] : null;
      return data is Map ? Map<String, dynamic>.from(data) : <String, dynamic>{};
    } on DioException catch (e) {
      throw PpudsApiException.fromDio(e);
    }
  }
}
