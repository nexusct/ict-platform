package com.ict.platform.integration.zoho;

import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.MediaType;
import org.springframework.stereotype.Service;
import org.springframework.util.LinkedMultiValueMap;
import org.springframework.util.MultiValueMap;
import org.springframework.web.reactive.function.BodyInserters;
import org.springframework.web.reactive.function.client.WebClient;

import java.time.Instant;
import java.util.Map;
import java.util.concurrent.atomic.AtomicReference;

@Service
@RequiredArgsConstructor
@Slf4j
public class ZohoAuthService {

    private final WebClient.Builder webClientBuilder;

    @Value("${zoho.crm.client-id:}")
    private String clientId;

    @Value("${zoho.crm.client-secret:}")
    private String clientSecret;

    @Value("${zoho.crm.refresh-token:}")
    private String refreshToken;

    @Value("${zoho.crm.auth-url:https://accounts.zoho.com/oauth/v2/token}")
    private String authUrl;

    private final AtomicReference<String> accessToken = new AtomicReference<>();
    private volatile Instant tokenExpiry = Instant.EPOCH;

    public String getAccessToken() {
        if (accessToken.get() == null || Instant.now().isAfter(tokenExpiry.minusSeconds(60))) {
            refreshAccessToken();
        }
        return accessToken.get();
    }

    private synchronized void refreshAccessToken() {
        if (accessToken.get() != null && Instant.now().isBefore(tokenExpiry.minusSeconds(60))) {
            return;
        }

        if (clientId.isBlank() || clientSecret.isBlank() || refreshToken.isBlank()) {
            log.warn("Zoho credentials not configured. Skipping token refresh.");
            return;
        }

        try {
            MultiValueMap<String, String> params = new LinkedMultiValueMap<>();
            params.add("grant_type", "refresh_token");
            params.add("client_id", clientId);
            params.add("client_secret", clientSecret);
            params.add("refresh_token", refreshToken);

            WebClient client = webClientBuilder.build();
            Map<?, ?> response = client.post()
                    .uri(authUrl)
                    .contentType(MediaType.APPLICATION_FORM_URLENCODED)
                    .body(BodyInserters.fromFormData(params))
                    .retrieve()
                    .bodyToMono(Map.class)
                    .block();

            if (response != null && response.containsKey("access_token")) {
                accessToken.set((String) response.get("access_token"));
                long expiresIn = response.containsKey("expires_in")
                        ? Long.parseLong(response.get("expires_in").toString())
                        : 3600L;
                tokenExpiry = Instant.now().plusSeconds(expiresIn);
                log.info("Zoho access token refreshed successfully");
            } else {
                log.error("Failed to refresh Zoho access token: {}", response);
            }
        } catch (Exception e) {
            log.error("Error refreshing Zoho access token: {}", e.getMessage(), e);
        }
    }
}
