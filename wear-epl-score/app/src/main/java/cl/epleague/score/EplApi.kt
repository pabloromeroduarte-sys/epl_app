package cl.epleague.score

import kotlinx.coroutines.Dispatchers
import kotlinx.coroutines.withContext
import org.json.JSONArray
import org.json.JSONObject
import java.io.IOException
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

data class PairStart(
    val deviceCode: String,
    val userCode: String,
    val verificationUrl: String,
    val pollIntervalSeconds: Long,
)

sealed interface PairStatus {
    data object Pending : PairStatus
    data object Expired : PairStatus
    data object Invalid : PairStatus
    data class Approved(val token: String) : PairStatus
}

class ApiException(message: String, val statusCode: Int = 0) : IOException(message)

class EplApi {
    suspend fun startPairing(): PairStart {
        val json = request("pair_start", "POST", body = JSONObject().put("device_name", "Samsung Galaxy Watch FE"))
        return PairStart(
            deviceCode = json.getString("device_code"),
            userCode = json.getString("user_code"),
            verificationUrl = json.getString("verification_url"),
            pollIntervalSeconds = json.optLong("poll_interval", 3L),
        )
    }

    suspend fun pairingStatus(deviceCode: String): PairStatus {
        val json = request("pair_status", "POST", body = JSONObject().put("device_code", deviceCode))
        return when (json.optString("status")) {
            "approved" -> PairStatus.Approved(json.getString("access_token"))
            "expired", "consumed" -> PairStatus.Expired
            "invalid" -> PairStatus.Invalid
            else -> PairStatus.Pending
        }
    }

    suspend fun matches(token: String): List<MatchInfo> {
        val json = request("matches", "GET", token)
        val array = json.optJSONArray("matches") ?: JSONArray()
        return buildList {
            for (index in 0 until array.length()) add(MatchInfo.fromApi(array.getJSONObject(index)))
        }
    }

    suspend fun submitResult(token: String, session: ScoreSession): String {
        val sets = JSONArray().also { array ->
            session.current.completedSets.forEach { set ->
                array.put(JSONObject().put("local", set.local).put("visitante", set.visitor))
            }
        }
        val body = JSONObject()
            .put("partido_id", session.match.id)
            .put("sets", sets)
            .put("idempotency_key", session.submissionKey)
        val json = request("result", "POST", token, body, session.submissionKey)
        return json.optString("message", "Resultado registrado correctamente.")
    }

    private suspend fun request(
        action: String,
        method: String,
        token: String = "",
        body: JSONObject? = null,
        idempotencyKey: String = "",
    ): JSONObject = withContext(Dispatchers.IO) {
        val encodedAction = URLEncoder.encode(action, Charsets.UTF_8.name())
        val separator = if (BuildConfig.API_URL.contains('?')) '&' else '?'
        val connection = (URL("${BuildConfig.API_URL}$separator" + "action=$encodedAction").openConnection() as HttpURLConnection)
        try {
            connection.requestMethod = method
            connection.connectTimeout = 12_000
            connection.readTimeout = 15_000
            connection.setRequestProperty("Accept", "application/json")
            connection.setRequestProperty("User-Agent", "EPL-Score-Wear/${BuildConfig.VERSION_NAME}")
            if (token.isNotBlank()) connection.setRequestProperty("Authorization", "Bearer $token")
            if (idempotencyKey.isNotBlank()) connection.setRequestProperty("Idempotency-Key", idempotencyKey)
            if (body != null) {
                connection.doOutput = true
                connection.setRequestProperty("Content-Type", "application/json; charset=utf-8")
                connection.outputStream.use { it.write(body.toString().toByteArray(Charsets.UTF_8)) }
            }

            val status = connection.responseCode
            val stream = if (status in 200..299) connection.inputStream else connection.errorStream
            val raw = stream?.bufferedReader(Charsets.UTF_8)?.use { it.readText() }.orEmpty()
            val json = runCatching { JSONObject(raw) }.getOrElse { JSONObject() }
            if (status !in 200..299 || !json.optBoolean("ok", status in 200..299)) {
                throw ApiException(json.optString("error", "EPL no respondió correctamente."), status)
            }
            json
        } finally {
            connection.disconnect()
        }
    }
}

