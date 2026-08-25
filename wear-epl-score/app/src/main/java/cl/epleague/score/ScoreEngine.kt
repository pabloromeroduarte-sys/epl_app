package cl.epleague.score

enum class Side { LOCAL, VISITOR }

object ScoreEngine {
    fun addPoint(session: ScoreSession, side: Side): ScoreSession {
        if (session.current.finished) return session
        val before = session.current
        val after = if (before.tieBreak) {
            addTieBreakPoint(before, side)
        } else {
            addRegularPoint(before, side, session.goldenPoint)
        }
        val history = (session.history + before).takeLast(300)
        return session.copy(current = after, history = history)
    }

    fun undo(session: ScoreSession): ScoreSession {
        if (session.history.isEmpty()) return session
        return session.copy(current = session.history.last(), history = session.history.dropLast(1))
    }

    fun pointLabels(session: ScoreSession): Pair<String, String> {
        val state = session.current
        if (state.tieBreak) return state.localPoints.toString() to state.visitorPoints.toString()

        if (!session.goldenPoint && state.localPoints >= 3 && state.visitorPoints >= 3) {
            return when {
                state.localPoints == state.visitorPoints -> "40" to "40"
                state.localPoints > state.visitorPoints -> "A" to "40"
                else -> "40" to "A"
            }
        }
        val labels = listOf("0", "15", "30", "40")
        return labels[state.localPoints.coerceAtMost(3)] to labels[state.visitorPoints.coerceAtMost(3)]
    }

    private fun addRegularPoint(state: ScoreSnapshot, side: Side, goldenPoint: Boolean): ScoreSnapshot {
        var local = state.localPoints
        var visitor = state.visitorPoints
        if (side == Side.LOCAL) local++ else visitor++

        val gameWon = if (goldenPoint) {
            (local >= 4 && visitor <= 3 && local > visitor) || (visitor >= 4 && local <= 3 && visitor > local)
        } else {
            (local >= 4 || visitor >= 4) && kotlin.math.abs(local - visitor) >= 2
        }
        return if (gameWon) winGame(state, if (local > visitor) Side.LOCAL else Side.VISITOR)
        else state.copy(localPoints = local, visitorPoints = visitor)
    }

    private fun addTieBreakPoint(state: ScoreSnapshot, side: Side): ScoreSnapshot {
        val local = state.localPoints + if (side == Side.LOCAL) 1 else 0
        val visitor = state.visitorPoints + if (side == Side.VISITOR) 1 else 0
        val won = (local >= 7 || visitor >= 7) && kotlin.math.abs(local - visitor) >= 2
        if (!won) return state.copy(localPoints = local, visitorPoints = visitor)

        val winner = if (local > visitor) Side.LOCAL else Side.VISITOR
        val set = if (winner == Side.LOCAL) CompletedSet(7, 6, local, visitor)
        else CompletedSet(6, 7, local, visitor)
        return completeSet(state, set)
    }

    private fun winGame(state: ScoreSnapshot, side: Side): ScoreSnapshot {
        val localGames = state.localGames + if (side == Side.LOCAL) 1 else 0
        val visitorGames = state.visitorGames + if (side == Side.VISITOR) 1 else 0

        if (localGames == 6 && visitorGames == 6) {
            return state.copy(
                localPoints = 0,
                visitorPoints = 0,
                localGames = localGames,
                visitorGames = visitorGames,
                tieBreak = true,
            )
        }
        val setWon = (localGames >= 6 || visitorGames >= 6) && kotlin.math.abs(localGames - visitorGames) >= 2
        if (setWon) return completeSet(state, CompletedSet(localGames, visitorGames))

        return state.copy(
            localPoints = 0,
            visitorPoints = 0,
            localGames = localGames,
            visitorGames = visitorGames,
        )
    }

    private fun completeSet(state: ScoreSnapshot, set: CompletedSet): ScoreSnapshot {
        val completed = state.completedSets + set
        val localSets = completed.count { it.local > it.visitor }
        val visitorSets = completed.size - localSets
        return ScoreSnapshot(
            completedSets = completed,
            finished = localSets == 2 || visitorSets == 2,
        )
    }
}

