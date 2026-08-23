package ir.rokh7.app;

import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.WindowManager;
import android.webkit.WebSettings;
import android.webkit.WebView;
import androidx.activity.OnBackPressedCallback;
import androidx.core.splashscreen.SplashScreen;
import com.getcapacitor.Bridge;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.WebViewListener;

public class MainActivity extends BridgeActivity {
    private static final long SPLASH_TIMEOUT_MS = 12000L;
    private static final String PORTAL_HOME_URL = "https://7rokh.ir/casting-portal/home.php";
    private static final String PORTAL_CART_URL = "https://7rokh.ir/casting-portal/cart.php";
    private volatile boolean pageReady = false;
    private final Handler mainHandler = new Handler(Looper.getMainLooper());

    @Override
    public void onCreate(Bundle savedInstanceState) {
        SplashScreen.installSplashScreen(this).setKeepOnScreenCondition(() -> !pageReady);

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
        getWindow().setFlags(
            WindowManager.LayoutParams.FLAG_SECURE,
            WindowManager.LayoutParams.FLAG_SECURE
        );
        getWindow().setBackgroundDrawableResource(R.drawable.splash);
        mainHandler.postDelayed(this::hideSplash, SPLASH_TIMEOUT_MS);
        registerBackNavigationHandler();
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
    }

    private void styleWebView(WebView webView) {
        if (webView == null) {
            return;
        }
        webView.setBackgroundColor(Color.TRANSPARENT);
        webView.setBackgroundResource(R.drawable.splash);
        WebView.setWebContentsDebuggingEnabled(false);
        WebSettings settings = webView.getSettings();
        settings.setAllowFileAccess(false);
        settings.setAllowContentAccess(false);
        settings.setSupportZoom(false);
        settings.setBuiltInZoomControls(false);
        settings.setDisplayZoomControls(false);
        settings.setMixedContentMode(WebSettings.MIXED_CONTENT_NEVER_ALLOW);
    }
}
