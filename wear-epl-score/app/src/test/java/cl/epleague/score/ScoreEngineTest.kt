package cl.epleague.score

import org.junit.Assert.assertEquals
import org.junit.Assert.assertTrue
import org.junit.Test

class ScoreEngineTest {
    @Test
    fun goldenPointClosesGameAfterDeuce() {
        var session = ScoreSession(MatchInfo.demo(), goldenPoint = true)
        repeat(3) { session = ScoreEngine.addPoint(session, Side.LOCAL) }
        repeat(3) { session = ScoreEngine.addPoint(session, Side.VISITOR) }
        session = ScoreEngine.addPoint(session, Side.LOCAL)
        assertEquals(1, session.current.localGames)
        assertEquals(0, session.current.localPoints)
    }

    @Test
    fun advantageReturnsToDeuce() {
        var session = ScoreSession(MatchInfo.demo(), goldenPoint = false)
        repeat(3) { session = ScoreEngine.addPoint(session, Side.LOCAL) }
        repeat(3) { session = ScoreEngine.addPoint(session, Side.VISITOR) }
        session = ScoreEngine.addPoint(session, Side.LOCAL)
        assertEquals("A" to "40", ScoreEngine.pointLabels(session))
        session = ScoreEngine.addPoint(session, Side.VISITOR)
        assertEquals("40" to "40", ScoreEngine.pointLabels(session))
    }

    @Test
    fun straightSetsFinishTheMatch() {
        var session = ScoreSession(MatchInfo.demo(), goldenPoint = true)
        repeat(12) {
            repeat(4) { session = ScoreEngine.addPoint(session, Side.LOCAL) }
        }
        assertTrue(session.current.finished)
        assertEquals(listOf(6, 6), session.current.completedSets.map { it.local })
        assertEquals(listOf(0, 0), session.current.completedSets.map { it.visitor })
    }

    @Test
    fun undoRestoresExactPreviousState() {
        val initial = ScoreSession(MatchInfo.demo(), goldenPoint = true)
        val changed = ScoreEngine.addPoint(initial, Side.VISITOR)
        assertEquals(initial.current, ScoreEngine.undo(changed).current)
    }
}

