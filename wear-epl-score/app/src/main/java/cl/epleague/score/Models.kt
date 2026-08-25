package cl.epleague.score

import org.json.JSONArray
import org.json.JSONObject
import java.util.UUID

data class MatchInfo(
    val id: Int,
    val leagueName: String,
    val localName: String,
    val visitorName: String,
    val scheduledAt: String,
    val venueName: String,
    val roundName: String,
    val mySide: String,
    val canSubmit: Boolean,
    val blockedReason: String,
    val demo: Boolean = false,
) {
    fun toJson(): JSONObject = JSONObject()
        .put("id", id)
        .put("leagueName", leagueName)
        .put("localName", localName)
        .put("visitorName", visitorName)
        .put("scheduledAt", scheduledAt)
        .put("venueName", venueName)
        .put("roundName", roundName)
        .put("mySide", mySide)
        .put("canSubmit", canSubmit)
        .put("blockedReason", blockedReason)
        .put("demo", demo)

    companion object {
        fun fromApi(json: JSONObject) = MatchInfo(
            id = json.getInt("id"),
            leagueName = json.optString("liga_nombre", "EPL"),
            localName = json.optString("local_nombre", "Local"),
            visitorName = json.optString("visitante_nombre", "Visitante"),
            scheduledAt = json.optNullableString("fecha_programada"),
            venueName = json.optNullableString("recinto_nombre"),
            roundName = json.optNullableString("nombre_fecha").ifBlank {
                json.optNullableString("jornada").let { if (it.isBlank()) "Partido" else "Jornada $it" }
            },
            mySide = json.optString("mi_lado", "local"),
            canSubmit = json.optBoolean("can_submit", false),
            blockedReason = json.optNullableString("blocked_reason"),
        )

        fun fromStored(json: JSONObject) = MatchInfo(
            id = json.getInt("id"),
            leagueName = json.optString("leagueName", "EPL"),
            localName = json.optString("localName", "Local"),
            visitorName = json.optString("visitorName", "Visitante"),
            scheduledAt = json.optString("scheduledAt", ""),
            venueName = json.optString("venueName", ""),
            roundName = json.optString("roundName", "Partido"),
            mySide = json.optString("mySide", "local"),
            canSubmit = json.optBoolean("canSubmit", true),
            blockedReason = json.optString("blockedReason", ""),
            demo = json.optBoolean("demo", false),
        )

        fun demo() = MatchInfo(
            id = -1,
            leagueName = "EPL · Partido de prueba",
            localName = "Tu pareja",
            visitorName = "Rivales",
            scheduledAt = "Hoy",
            venueName = "Cancha de prueba",
            roundName = "Demo",
            mySide = "local",
            canSubmit = true,
            blockedReason = "",
            demo = true,
        )
    }
}

data class CompletedSet(
    val local: Int,
    val visitor: Int,
    val tieLocal: Int = 0,
    val tieVisitor: Int = 0,
) {
    fun toJson(): JSONObject = JSONObject()
        .put("local", local)
        .put("visitor", visitor)
        .put("tieLocal", tieLocal)
        .put("tieVisitor", tieVisitor)

    companion object {
        fun fromJson(json: JSONObject) = CompletedSet(
            json.getInt("local"),
            json.getInt("visitor"),
            json.optInt("tieLocal"),
            json.optInt("tieVisitor"),
        )
    }
}

data class ScoreSnapshot(
    val localPoints: Int = 0,
    val visitorPoints: Int = 0,
    val localGames: Int = 0,
    val visitorGames: Int = 0,
    val tieBreak: Boolean = false,
    val completedSets: List<CompletedSet> = emptyList(),
    val finished: Boolean = false,
) {
    fun toJson(): JSONObject = JSONObject()
        .put("localPoints", localPoints)
        .put("visitorPoints", visitorPoints)
        .put("localGames", localGames)
        .put("visitorGames", visitorGames)
        .put("tieBreak", tieBreak)
        .put("finished", finished)
        .put("completedSets", JSONArray().also { array -> completedSets.forEach { array.put(it.toJson()) } })

    companion object {
        fun fromJson(json: JSONObject): ScoreSnapshot {
            val setsJson = json.optJSONArray("completedSets") ?: JSONArray()
            val sets = buildList {
                for (index in 0 until setsJson.length()) add(CompletedSet.fromJson(setsJson.getJSONObject(index)))
            }
            return ScoreSnapshot(
                localPoints = json.optInt("localPoints"),
                visitorPoints = json.optInt("visitorPoints"),
                localGames = json.optInt("localGames"),
                visitorGames = json.optInt("visitorGames"),
                tieBreak = json.optBoolean("tieBreak"),
                completedSets = sets,
                finished = json.optBoolean("finished"),
            )
        }
    }
}

data class ScoreSession(
    val match: MatchInfo,
    val goldenPoint: Boolean,
    val current: ScoreSnapshot = ScoreSnapshot(),
    val history: List<ScoreSnapshot> = emptyList(),
    val submissionKey: String = UUID.randomUUID().toString(),
) {
    fun toJson(): JSONObject = JSONObject()
        .put("match", match.toJson())
        .put("goldenPoint", goldenPoint)
        .put("current", current.toJson())
        .put("submissionKey", submissionKey)
        .put("history", JSONArray().also { array -> history.forEach { array.put(it.toJson()) } })

    companion object {
        fun fromJson(json: JSONObject): ScoreSession {
            val historyJson = json.optJSONArray("history") ?: JSONArray()
            val history = buildList {
                for (index in 0 until historyJson.length()) add(ScoreSnapshot.fromJson(historyJson.getJSONObject(index)))
            }
            return ScoreSession(
                match = MatchInfo.fromStored(json.getJSONObject("match")),
                goldenPoint = json.optBoolean("goldenPoint", true),
                current = ScoreSnapshot.fromJson(json.getJSONObject("current")),
                history = history,
                submissionKey = json.optString("submissionKey").ifBlank { UUID.randomUUID().toString() },
            )
        }
    }
}

fun JSONObject.optNullableString(key: String): String =
    if (!has(key) || isNull(key)) "" else optString(key, "")

