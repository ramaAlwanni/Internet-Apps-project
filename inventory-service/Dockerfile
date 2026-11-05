FROM maven:3.9-eclipse-temurin-21 AS build

WORKDIR /workspace

COPY pom.xml .
COPY src ./src

RUN mvn -B -DskipTests package

FROM eclipse-temurin:21-jre

WORKDIR /app

COPY --from=build /workspace/target/inventory-service-*.jar /app/inventory-service.jar

EXPOSE 8081 9090

ENTRYPOINT ["java", "-jar", "/app/inventory-service.jar"]
