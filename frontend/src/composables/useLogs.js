import { ref } from 'vue'
import { apiRequest } from '../api/client'
import { MEDIA_BASE } from '../api/client'

const logs = ref([])
const total = ref(0)

function ensureArray(value) {
  return Array.isArray(value) ? value : []
}

function normalizeCoAuthors(coAuthors) {
  return ensureArray(coAuthors).map((item) => {
    if (typeof item === 'string') {
      return { id: crypto.randomUUID(), name: item }
    }
    return { id: item?.id || crypto.randomUUID(), name: item?.name || '' }
  })
}

function normalizeShuffleTime(shuffleTime) {
  return ensureArray(shuffleTime).map((item) => {
    if (typeof item === 'string') {
      return { id: crypto.randomUUID(), arcana: item, cardEffect: '', notes: '' }
    }
    return {
      id: item?.id || crypto.randomUUID(),
      arcana: item?.arcana || '',
      cardEffect: item?.cardEffect || '',
      notes: item?.notes || '',
    }
  })
}

function normalizeDiscoveries(discoveries) {
  return ensureArray(discoveries).map((item) => {
    if (typeof item === 'string') {
      return { id: crypto.randomUUID(), discovery: item, details: '' }
    }
    return { id: item?.id || crypto.randomUUID(), discovery: item?.discovery || '', details: item?.details || '' }
  })
}

function normalizeCustomInfo(customInfo) {
  return ensureArray(customInfo).map((item) => {
    if (typeof item === 'string') {
      return { id: crypto.randomUUID(), label: item, value: '' }
    }
    return { id: item?.id || crypto.randomUUID(), label: item?.label || '', value: item?.value || '' }
  })
}

function normalizeTreasure(treasure) {
  return ensureArray(treasure).map((item) => {
    if (typeof item === 'string') {
      return { id: crypto.randomUUID(), itemName: item, source: '' }
    }
    return { id: item?.id || crypto.randomUUID(), itemName: item?.itemName || '', source: item?.source || '' }
  })
}

function normalizeShadows(shadows) {
  return ensureArray(shadows).map((item) => ({
    id: item?.id || crypto.randomUUID(),
    name: item?.name || '',
    floorEncountered: item?.floorEncountered || '',
    arcana: item?.arcana || '',
    weakness: item?.weakness || '',
    resistances: item?.resistances || '',
    dangerousSkills: item?.dangerousSkills || '',
    strategyNotes: item?.strategyNotes || '',
  }))
}


function normalizeMedia(media) {
    return ensureArray(media)
        .filter((item) => item && item.url)
        .map((item) => ({
            id: item.id,
            type: item.type,
            url: item.url.startsWith('http')
                ? item.url
                : `${MEDIA_BASE}${item.url}`,
            caption: item.caption || '',
        }))
}

function normalizeEntry(rawEntry) {
  if (!rawEntry || typeof rawEntry !== 'object') return rawEntry

  return {
    ...rawEntry,
    coAuthors: normalizeCoAuthors(rawEntry.coAuthors),
    shuffleTime: normalizeShuffleTime(rawEntry.shuffleTime),
    discoveries: normalizeDiscoveries(rawEntry.discoveries),
    customInfo: normalizeCustomInfo(rawEntry.customInfo),
    treasure: normalizeTreasure(rawEntry.treasure),
    shadows: normalizeShadows(rawEntry.shadows),
    media: normalizeMedia(rawEntry.media),
    gatekeeper: {
      encountered: false,
      bossName: '',
      floor: null,
      weakness: '',
      mechanics: '',
      recommendedLevel: null,
      strategy: '',
      ...rawEntry.gatekeeper,
    },
  }
}

function buildMediaPayload(media) {
    return ensureArray(media)
        .filter((item) => item && item.url)
        .map((item) => {
            let url = item.url

            // Convert the display URL back to the backend-relative path before sending it to PHP.
            if (url.startsWith(MEDIA_BASE)) {
                url = url.slice(MEDIA_BASE.length)
            }

            return {
                id: item.id,
                type: item.type,
                url,
                caption: item.caption || '',
            }
        })
}

function buildPayload(entry) {
  return {
    title: entry.title ?? null,
    date: entry.date,
    block: entry.block ?? '',
    floor: entry.floor ?? entry.startFloor ?? null,
    startFloor: entry.startFloor ?? null,
    endFloor: entry.endFloor ?? null,
    blocksCovered: entry.blocksCovered ?? [],
    coAuthors: entry.coAuthors ?? [],
    partyMembers: entry.partyMembers ?? [],
    difficulty: entry.difficulty ?? null,
    explorationGoal: entry.explorationGoal ?? null,
    outcome: entry.outcome ?? null,
    strategyNotes: entry.strategyNotes,
    overallNotes: entry.overallNotes ?? null,
    shadows: entry.shadows ?? [],
    gatekeeper: entry.gatekeeper ?? { encountered: false },
    treasure: entry.treasure ?? [],
    shuffleTime: entry.shuffleTime ?? [],
    discoveries: entry.discoveries ?? [],
    customInfo: entry.customInfo ?? [],
    media: buildMediaPayload(entry.media),
  }
}

export function clearVotes() {
  logs.value.forEach((log) => {
    log.hasVoted = false
  })
}

export function useLogs() {
  async function fetchLogs(filters = {}) {
    const query = new URLSearchParams(
      Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined)
    ).toString()

    const res = await apiRequest(`/logs.php?${query}`)
    logs.value = res.data.map(normalizeEntry)
    total.value = res.total
    return res
  }

  async function fetchLogById(id) {
    const res = await apiRequest(`/logs.php?id=${id}`)
    return normalizeEntry(res.data)
  }

  function hasVoted(logId) {
    return Boolean(logs.value.find((l) => l.id === logId)?.hasVoted)
  }

  async function addLog(entry) {
    const res = await apiRequest('/logs.php', {
      method: 'POST',
      body: JSON.stringify(buildPayload(entry)),
    })
    return res.id
  }

  async function updateLog(id, updates) {
    const existing = logs.value.find((l) => l.id === id)

    const entry = existing
      ? { ...existing, ...updates }
      : updates

    await apiRequest(`/logs.php?id=${id}`, {
      method: 'PUT',
      body: JSON.stringify(buildPayload(entry)),
    })

    const index = logs.value.findIndex((l) => l.id === id)

    if (index !== -1) {
      logs.value[index] = normalizeEntry({
        ...logs.value[index],
        ...updates,
      })
    }
  }

  async function deleteLog(id) {
    await apiRequest(`/logs.php?id=${id}`, { method: 'DELETE' })
    logs.value = logs.value.filter((l) => l.id !== id)
  }

  async function upvote(id) {
    try {
      const res = await apiRequest('/votes.php', {
        method: 'POST',
        body: JSON.stringify({ log_id: id }),
      })
      const entry = logs.value.find((l) => l.id === id)
      if (entry) {
        entry.votes = res.votes
        // Updates dynamically based on toggle response (true on vote, false on unvote)
        entry.hasVoted = Boolean(res.voted)
      }
      return res
    } catch (e) {
      throw e
    }
  }

  return { logs, total, fetchLogs, fetchLogById, addLog, updateLog, deleteLog, upvote, hasVoted }
}