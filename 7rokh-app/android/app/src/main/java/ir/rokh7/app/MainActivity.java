package ir.rokh7.app;

import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.graphics.Color;
import android.graphics.drawable.ColorDrawable;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.WindowManager;
import android.webkit.JavascriptInterface;
import android.webkit.WebSettings;
import android.webkit.WebView;
import androidx.activity.OnBackPressedCallback;
import androidx.core.splashscreen.SplashScreen;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.WebViewListener;
import java.util.concurrent.CountDownLatch;
import java.util.concurrent.TimeUnit;

public class MainActivity extends BridgeActivity {
    private static final long SPLASH_TIMEOUT_MS = 12000L;
    private static final int APP_WINDOW_COLOR = Color.parseColor("#F3EEE4");
    private static final String PORTAL_HOME_URL = "https://7rokh.com/casting-portal/home.php";
    private static final String PORTAL_CART_URL = "https://7rokh.com/casting-portal/cart.php";
    private volatile boolean pageReady = false;
    private final Handler mainHandler = new Handler(Looper.getMainLooper());

    @Override
    public void onCreate(Bundle savedInstanceState) {
        SplashScreen splash = SplashScreen.installSplashScreen(this);
        splash.setKeepOnScreenCondition(() -> !pageReady);
        splash.setOnExitAnimationListener(provider -> provider.remove());

        this.bridgeBuilder.addWebViewListener(new WebViewListener() {
            @Override
            public void onPageStarted(WebView webView) {
                styleWebView(webView);
            }

            @Override
            public void onPageLoaded(WebView webView) {
                styleWebView(webView);
                hideSplash();
            }

            @Override
            public void onReceivedError(WebView webView) {
                hideSplash();
            }

            @Override
            public void onReceivedHttpError(WebView webView) {
                hideSplash();
            }
        });

        super.onCreate(savedInstanceState);
        allowPasteCapture();
        clearSplashWindowBackground();
        Bridge bridge = getBridge();
        if (bridge != null) {
            attachAppBridge(bridge.getWebView());
        }
        mainHandler.postDelayed(this::hideSplash, SPLASH_TIMEOUT_MS);
        registerBackNavigationHandler();
    }

    @Override
    public void onResume() {
        super.onResume();
        allowPasteCapture();
        clearSplashWindowBackground();
    }

    @Override
    public void onWindowFocusChanged(boolean hasFocus) {
        super.onWindowFocusChanged(hasFocus);
        if (hasFocus) {
            allowPasteCapture();
            clearSplashWindowBackground();
        }
    }

    private void allowPasteCapture() {
        getWindow().clearFlags(WindowManager.LayoutParams.FLAG_SECURE);
    }

    private void clearSplashWindowBackground() {
        getWindow().clearFlags(WindowManager.LayoutParams.FLAG_SECURE);
        setTheme(R.style.AppTheme_NoActionBar);
        getWindow().setBackgroundDrawable(new ColorDrawable(APP_WINDOW_COLOR));
    }

    private void attachAppBridge(WebView webView) {
        if (webView == null) {
            return;
        }
        webView.addJavascriptInterface(new CastingAppBridge(), "CastingApp");
    }

    public class CastingAppBridge {
        @JavascriptInterface
        public void setSecureCapture(boolean secure) {
            runOnUiThread(() -> allowPasteCapture());
        }

        @JavascriptInterface
        public String getClipboardText() {
            if (Looper.myLooper() == Looper.getMainLooper()) {
                return readClipboardNow();
            }
            final String[] out = {""};
            final CountDownLatch latch = new CountDownLatch(1);
            mainHandler.post(() -> {
                try {
                    out[0] = readClipboardNow();
                } finally {
                    latch.countDown();
                }
            });
            try {
                latch.await(2000, TimeUnit.MILLISECONDS);
            } catch (InterruptedException ignored) {
                Thread.currentThread().interrupt();
            }
            return out[0] != null ? out[0] : "";
        }
    }

    private volatile String lastClipCache = "";

    @SuppressWarnings("deprecation")
    private String readClipboardNow() {
        try {
            ClipboardManager clipboard = (ClipboardManager) getSystemService(Context.CLIPBOARD_SERVICE);
            if (clipboard == null) {
                return lastClipCache;
            }
            ClipData clip = clipboard.getPrimaryClip();
            if (clip != null && clip.getItemCount() > 0) {
                CharSequence text = clip.getItemAt(0).coerceToText(this);
                if (text != null && text.length() > 0) {
                    lastClipCache = text.toString();
                    return lastClipCache;
                }
            }
            CharSequence legacy = clipboard.getText();
            if (legacy != null && legacy.length() > 0) {
                lastClipCache = legacy.toString();
                return lastClipCache;
            }
        } catch (Exception ignored) {
            /* ignore */
        }
        return lastClipCache != null ? lastClipCache : "";
    }

    private void registerBackNavigationHandler() {
        getOnBackPressedDispatcher().addCallback(this, new OnBackPressedCallback(true) {
            @Override
            public void handleOnBackPressed() {
                Bridge bridge = getBridge();
                WebView webView = bridge != null ? bridge.getWebView() : null;
                if (webView != null && webView.canGoBack()) {
                    webView.goBack();
                    return;
                }
                if (webView != null && !isPortalHomeUrl(webView.getUrl())) {
                    webView.loadUrl(resolveFallbackUrl(webView.getUrl()));
                    return;
                }
                setEnabled(false);
                getOnBackPressedDispatcher().onBackPressed();
            }
        });
    }

    private String resolveFallbackUrl(String url) {
        if (url == null || url.isEmpty()) {
            return PORTAL_HOME_URL;
        }
        String lower = url.toLowerCase();
        if (lower.contains("checkout")
            || lower.contains("cart.php")
            || lower.contains("membership")
            || lower.contains("shaparak")
            || lower.contains("behpardakht")) {
            return PORTAL_CART_URL;
        }
        return PORTAL_HOME_URL;
    }

    private boolean isPortalHomeUrl(String url) {
        if (url == null || url.isEmpty()) {
            return false;
        }
        return url.contains("/casting-portal/home.php")
            || url.endsWith("/casting-portal/")
            || url.endsWith("/casting-portal");
    }

    private void hideSplash() {
        pageReady = true;
        clearSplashWindowBackground();
    }

    private void styleWebView(WebView webView) {
        if (webView == null) {
            return;
        }
        allowPasteCapture();
        attachAppBridge(webView);
        webView.setLongClickable(false);
        webView.setHapticFeedbackEnabled(false);
        webView.setBackgroundColor(APP_WINDOW_COLOR);
        WebView.setWebContentsDebuggingEnabled(false);
        WebSettings settings = webView.getSettings();
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(false);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setUseWideViewPort(true);
        settings.setLoadWithOverviewMode(true);
        settings.setTextZoom(100);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
    }
}
