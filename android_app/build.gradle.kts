// Top-level build file where you can add configuration options common to all sub-projects/modules.
plugins {
    alias(libs.plugins.android.application) apply false
    alias(libs.plugins.kotlin.android) apply false
    alias(libs.plugins.kotlin.compose) apply false
    alias(libs.plugins.google.services) apply false
}

subprojects {
    tasks.register("genDict") {
        // tell others this file cannot be replaced (final)
        val dictDest = project.file(".cache/builddict.txt")
        // prevent duplicate
        println("${dictDest.absolutePath} exists ${dictDest.exists()}")
        if (!dictDest.exists()) {
            outputs.file(dictDest)
            doLast {
                val r = java.util.Random()
                println(r)
                val begin = r.nextInt(1000) + 0x0100
                val end = begin + 0x40000
                println("end: $end")
                val chars = (begin..end)
                    .filter { Character.isValidCodePoint(it) && Character.isJavaIdentifierPart(it) }
                    .map { String(Character.toChars(it)) }
                println("chars: $chars")
                val max = chars.size
                println(max)
                val start = mutableListOf<String>()
                for (i in 0 until max) {
                    val c = chars[i][0]
                    if (Character.isJavaIdentifierStart(c)) {
                        start.add(c.toString())
                    }
                }
                println(start.size)
                val f = outputs.files.singleFile
                f.parentFile.mkdirs()
                f.writeText(start.joinToString(System.lineSeparator()) + System.lineSeparator(), Charsets.UTF_8)
            }
        }
    }

    afterEvaluate {
        // val isAndroid = project.hasProperty("android")
        val isAndroidApplication = project.plugins.hasPlugin("com.android.application")
        // val isAndroidLibrary = project.plugins.hasPlugin("com.android.library")

        // println("project name ${project.name} android=$isAndroid androidApplication=$isAndroidApplication androidLibrary=$isAndroidLibrary")

        tasks.configureEach {
            // Disable tasks
            // val isTests = name.contains(Regex("(test|lint|check)", RegexOption.IGNORE_CASE)) ||
            //         name.contains(Regex("^(analyze)", RegexOption.IGNORE_CASE))
            // if (isTests) {
            //     enabled = false
            // }

            if (isAndroidApplication) {
                if (name == "assembleRelease") {
                    dependsOn(tasks.named("genDict"))
                }
            }
        }
    }
}
