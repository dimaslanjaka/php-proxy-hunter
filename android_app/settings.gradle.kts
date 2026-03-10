pluginManagement {
  repositories {
    google {
      content {
        includeGroupByRegex("com\\.android.*")
        includeGroupByRegex("com\\.google.*")
        includeGroupByRegex("androidx.*")
      }
    }
    mavenCentral()
    mavenLocal()
    gradlePluginPortal()
  }
}
plugins {
  id("org.gradle.toolchains.foojay-resolver-convention") version "0.10.0"
}
dependencyResolutionManagement {
  repositoriesMode.set(RepositoriesMode.FAIL_ON_PROJECT_REPOS)
  repositories {
    google()
    mavenCentral()
    mavenLocal()
    maven { url = uri("https://s01.oss.sonatype.org/content/repositories/snapshots/") }
    maven { url = uri("https://jitpack.io") }
    maven { url = uri("https://oss.sonatype.org/content/repositories/snapshots/") }
    maven {
      name = "Mozilla Nightly"
      url = uri("https://nightly.maven.mozilla.org/maven2")
    }
    maven {
      name = "Mozilla"
      url = uri("https://maven.mozilla.org/maven2")
    }
  }
}

rootProject.name = "Proxy Hunter"
include(":app")

//include(":library-jvm")
//project(":library-jvm").projectDir = file("D:/Repositories/android-traffic/library-jvm")
//
//include(":library")
//project(":library").projectDir = file("D:/Repositories/android-traffic/library")
//
//include(":library-vpn")
//project(":library-vpn").projectDir = file("D:/Repositories/android-traffic/library-vpn")
//
//include(":tun2socks")
//project(":tun2socks").projectDir = file("D:/Repositories/android-traffic/tun2socks")
//
//include(":powertunnel")
//project(":powertunnel").projectDir = file("D:/Repositories/android-traffic/powertunnel")
//
//include(":theme")
//project(":theme").projectDir = file("D:/Repositories/android-traffic/theme")
