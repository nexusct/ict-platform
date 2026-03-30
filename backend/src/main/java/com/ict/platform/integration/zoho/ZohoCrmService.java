package com.ict.platform.integration.zoho;

import com.fasterxml.jackson.databind.ObjectMapper;
import com.ict.platform.entity.Project;
import lombok.RequiredArgsConstructor;
import lombok.extern.slf4j.Slf4j;
import org.springframework.beans.factory.annotation.Value;
import org.springframework.http.HttpHeaders;
import org.springframework.http.MediaType;
import org.springframework.stereotype.Service;
import org.springframework.web.reactive.function.client.WebClient;

import java.util.HashMap;
import java.util.List;
import java.util.Map;

@Service
@RequiredArgsConstructor
@Slf4j
public class ZohoCrmService {

    private final ZohoAuthService zohoAuthService;
    private final WebClient.Builder webClientBuilder;
    private final ObjectMapper objectMapper;

    @Value("${zoho.crm.base-url:https://www.zohoapis.com/crm/v2}")
    private String baseUrl;

    public Map<String, Object> createDeal(Project project) {
        String token = zohoAuthService.getAccessToken();
        if (token == null) {
            log.warn("No Zoho access token available. Skipping CRM sync.");
            return Map.of();
        }

        Map<String, Object> deal = buildDealPayload(project);
        Map<String, Object> requestBody = Map.of("data", List.of(deal));

        try {
            Map<?, ?> response = webClientBuilder.build()
                    .post()
                    .uri(baseUrl + "/Deals")
                    .header(HttpHeaders.AUTHORIZATION, "Zoho-oauthtoken " + token)
                    .contentType(MediaType.APPLICATION_JSON)
                    .bodyValue(requestBody)
                    .retrieve()
                    .bodyToMono(Map.class)
                    .block();

            log.info("Created deal in Zoho CRM for project: {}", project.getProjectNumber());
            return response != null ? (Map<String, Object>) response : Map.of();
        } catch (Exception e) {
            log.error("Failed to create deal in Zoho CRM: {}", e.getMessage(), e);
            throw new RuntimeException("Zoho CRM sync failed: " + e.getMessage(), e);
        }
    }

    public Map<String, Object> updateDeal(String dealId, Project project) {
        String token = zohoAuthService.getAccessToken();
        if (token == null) {
            log.warn("No Zoho access token available. Skipping CRM sync.");
            return Map.of();
        }

        Map<String, Object> deal = buildDealPayload(project);
        deal.put("id", dealId);
        Map<String, Object> requestBody = Map.of("data", List.of(deal));

        try {
            Map<?, ?> response = webClientBuilder.build()
                    .put()
                    .uri(baseUrl + "/Deals/" + dealId)
                    .header(HttpHeaders.AUTHORIZATION, "Zoho-oauthtoken " + token)
                    .contentType(MediaType.APPLICATION_JSON)
                    .bodyValue(requestBody)
                    .retrieve()
                    .bodyToMono(Map.class)
                    .block();

            log.info("Updated deal {} in Zoho CRM", dealId);
            return response != null ? (Map<String, Object>) response : Map.of();
        } catch (Exception e) {
            log.error("Failed to update deal in Zoho CRM: {}", e.getMessage(), e);
            throw new RuntimeException("Zoho CRM sync failed: " + e.getMessage(), e);
        }
    }

    public void deleteDeal(String dealId) {
        String token = zohoAuthService.getAccessToken();
        if (token == null) {
            log.warn("No Zoho access token available. Skipping CRM sync.");
            return;
        }

        try {
            webClientBuilder.build()
                    .delete()
                    .uri(baseUrl + "/Deals/" + dealId)
                    .header(HttpHeaders.AUTHORIZATION, "Zoho-oauthtoken " + token)
                    .retrieve()
                    .toBodilessEntity()
                    .block();

            log.info("Deleted deal {} from Zoho CRM", dealId);
        } catch (Exception e) {
            log.error("Failed to delete deal from Zoho CRM: {}", e.getMessage(), e);
            throw new RuntimeException("Zoho CRM sync failed: " + e.getMessage(), e);
        }
    }

    private Map<String, Object> buildDealPayload(Project project) {
        Map<String, Object> deal = new HashMap<>();
        deal.put("Deal_Name", project.getProjectNumber() + " - " + project.getName());
        deal.put("Description", project.getDescription());
        deal.put("Stage", mapStatusToZohoStage(project.getStatus()));
        if (project.getBudget() != null) {
            deal.put("Amount", project.getBudget());
        }
        if (project.getStartDate() != null) {
            deal.put("Closing_Date", project.getEndDate() != null ? project.getEndDate().toString() : null);
        }
        deal.put("Account_Name", project.getClientName());
        return deal;
    }

    private String mapStatusToZohoStage(Project.ProjectStatus status) {
        return switch (status) {
            case PENDING -> "Qualification";
            case ACTIVE -> "Value Proposition";
            case ON_HOLD -> "Perception Analysis";
            case COMPLETED -> "Closed Won";
            case CANCELLED -> "Closed Lost";
        };
    }
}
