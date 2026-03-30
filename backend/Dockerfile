FROM eclipse-temurin:17-jdk-alpine AS build
WORKDIR /app

COPY pom.xml .
COPY src src

RUN apk add --no-cache maven && \
    mvn clean package -DskipTests -q

FROM eclipse-temurin:17-jre-alpine
WORKDIR /app

RUN addgroup -S ict && adduser -S ict -G ict

COPY --from=build /app/target/*.jar app.jar

RUN mkdir -p /app/logs && chown -R ict:ict /app
USER ict

EXPOSE 8080

HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
  CMD wget -q --spider http://localhost:8080/api/v1/actuator/health || exit 1

ENTRYPOINT ["java", \
  "-XX:+UseContainerSupport", \
  "-XX:MaxRAMPercentage=75.0", \
  "-Djava.security.egd=file:/dev/./urandom", \
  "-jar", "app.jar"]
