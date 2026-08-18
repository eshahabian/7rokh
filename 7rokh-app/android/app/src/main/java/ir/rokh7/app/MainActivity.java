package ir.rokh7.app;

import android.graphics.Color;
import android.os.Bundle;
import android.os.Handler;
import android.os.Looper;
import android.view.WindowManager;
import android.webkit.WebView;
import androidx.core.splashscreen.SplashScreen;
import com.getcapacitor.BridgeActivity;
import com.getcapacitor.WebViewListener;

public class MainActivity extends BridgeActivity {
    private static final long SPLASH_TIMEOUT_MS = 12000L;
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
    }
}
