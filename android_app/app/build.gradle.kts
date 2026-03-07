import org.jetbrains.kotlin.gradle.dsl.JvmTarget

plugins {
  alias(libs.plugins.android.application)
  alias(libs.plugins.kotlin.android)
  alias(libs.plugins.kotlin.compose)
}

android {
  namespace = "com.dimaslanjaka.proxyhunter"
  compileSdk = 36

  defaultConfig {
    applicationId = "com.dimaslanjaka.proxyhunter"
    minSdk = 24
    targetSdk = 36
    versionCode = 1
    versionName = "1.0"

    testInstrumentationRunner = "androidx.test.runner.AndroidJUnitRunner"
  }

  buildTypes {
    release {
      isMinifyEnabled = false
      proguardFiles(
        getDefaultProguardFile("proguard-android-optimize.txt"),
        "proguard-rules.pro"
      )
    }
    debug {
      isMinifyEnabled = true
      proguardFiles(
        getDefaultProguardFile("proguard-android-optimize.txt"),
        "proguard-rules.pro",
        "proguard-debug.pro"
      )
    }
  }
  compileOptions {
    sourceCompatibility = JavaVersion.VERSION_11
    targetCompatibility = JavaVersion.VERSION_11
    isCoreLibraryDesugaringEnabled = true
  }
  buildFeatures {
    compose = true
  }
  packaging {
    jniLibs {
      useLegacyPackaging = true
    }
    resources {
      excludes += listOf(
        "DebugProbesKt.bin",
        "**/*.ignore",
        "**/*.ignored",
        "META-INF/DEPENDENCIES",
        "META-INF/LICENSE",
        "META-INF/license.txt",
        "META-INF/NOTICE.txt",
        "META-INF/notice.txt",
        "META-INF/ASL2.0",
        "META-INF/*.kotlin_module",
        "mozilla/public-suffix-list.txt",
        "/META-INF/INDEX.LIST"
      )
      merges += listOf("**/LICENSE.txt", "**/NOTICE.txt")
    }
  }
}

kotlin {
  compilerOptions {
    jvmTarget.set(JvmTarget.JVM_11)
  }
}

// Global exclusions to resolve duplicate class errors with powertunnel-release.aar
// We exclude from runtime/implementation to avoid duplicates in the APK,
// but keep them for compilation to avoid compiler errors.
configurations.all {
  if (name.contains("implementation", ignoreCase = true) || name.contains("runtime", ignoreCase = true)) {
    exclude(group = "org.jetbrains", module = "annotations")
    exclude(group = "com.google.code.gson", module = "gson")
  }
}

dependencies {
  coreLibraryDesugaring(libs.desugar.jdk.libs)
  implementation(libs.androidx.core.ktx)
  implementation(libs.androidx.lifecycle.runtime.ktx)
  implementation(libs.androidx.activity.compose)
  implementation(platform(libs.androidx.compose.bom))
  implementation(libs.androidx.ui)
  implementation(libs.androidx.ui.graphics)
  implementation(libs.androidx.ui.tooling.preview)
  implementation(libs.androidx.material3)
  implementation(libs.androidx.material.icons.core)
  implementation(libs.androidx.material.icons.extended)
  implementation(libs.androidx.preference.ktx)
  testImplementation(libs.junit)
  androidTestImplementation(libs.androidx.junit)
  androidTestImplementation(libs.androidx.espresso.core)
  androidTestImplementation(platform(libs.androidx.compose.bom))
  androidTestImplementation(libs.androidx.ui.test.junit4)
  debugImplementation(libs.androidx.ui.tooling)
  debugImplementation(libs.androidx.ui.test.manifest)

  implementation(libs.okhttp)
  implementation(libs.mysql.connector)
  implementation(libs.timber)

  // Provide annotations to the compiler but don't package them
  compileOnly("org.jetbrains:annotations:23.0.0")
  compileOnly("com.google.code.gson:gson:2.10.1")

  // Register all .jar and .aar files from the specified directory
  // Exclude annotations and gson jars as they are already bundled in powertunnel-release.aar
  implementation(fileTree("D:\\Repositories\\android-traffic\\releases") {
    include("*.jar")
    include("*.aar")
    exclude("annotations-*.jar")
    exclude("gson-*.jar")
  })
}
