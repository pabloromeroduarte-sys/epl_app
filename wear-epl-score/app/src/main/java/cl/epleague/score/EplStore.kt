package cl.epleague.score

import android.content.Context
import androidx.core.content.edit
import org.json.JSONObject

class EplStore(context: Context) {
    private val preferences = context.getSharedPreferences("epl_score", Context.MODE_PRIVATE)

    fun accessToken(): String = preferences.getString("access_token", "").orEmpty()

    fun saveAccessToken(token: String) {
        preferences.edit { putString("access_token", token) }
    }

    fun clearAccessToken() {
        preferences.edit { remove("access_token") }
    }

    fun activeSession(): ScoreSession? {
        val raw = preferences.getString("active_score", null) ?: return null
        return runCatching { ScoreSession.fromJson(JSONObject(raw)) }.getOrNull()
    }

    fun saveSession(session: ScoreSession) {
        preferences.edit { putString("active_score", session.toJson().toString()) }
    }

    fun clearSession() {
        preferences.edit { remove("active_score") }
    }
}
