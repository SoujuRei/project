function arcanaIndex(races, arcana) {
  const target = (arcana || '').trim().toLowerCase()
  return races.findIndex((r) => r.trim().toLowerCase() === target)
}

function normalizeName(value) {
  return String(value || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '')
}

// Dyad Fusion: two Personas -> one result, via Arcana chart lookup
export function getDyadResult(personaA, personaB, personas, fusionChart) {
  const { races, table } = fusionChart
  let i = arcanaIndex(races, personaA.arcana)
  let j = arcanaIndex(races, personaB.arcana)
  if (i === -1 || j === -1) return null
  if (i < j) [i, j] = [j, i]

  const resultArcana = i === j ? personaA.arcana : table[i][j]
  if (!resultArcana || resultArcana === '-') return null

  const targetLevel = Math.ceil((Number(personaA.level) || 0 + Number(personaB.level) || 0) / 2)

  return personas
    .filter((p) => p.arcana === resultArcana)
    .sort((a, b) => Math.abs(Number(a.level) - targetLevel) - Math.abs(Number(b.level) - targetLevel))[0] ?? null
}

// Reverse search: given a target Persona, find every valid input pair
export function findDyadInputs(targetPersona, personas, fusionChart) {
  const { races, table } = fusionChart
  const results = []

  for (let i = 0; i < races.length; i++) {
    for (let j = 0; j <= i; j++) {
      const resultArcana = i === j ? races[i] : table[i][j]
      if (!resultArcana || normalizeName(resultArcana) !== normalizeName(targetPersona.arcana)) continue

      const candidatesA = personas.filter((p) => normalizeName(p.arcana) === normalizeName(races[i]))
      const candidatesB = personas.filter((p) => normalizeName(p.arcana) === normalizeName(races[j]))

      for (const a of candidatesA) {
        for (const b of candidatesB) {
          if (a.name === b.name) continue
          results.push({ inputA: a, inputB: b })
        }
      }
    }
  }
  return results
}

// Special Fusion: direct lookup, ingredients are already fixed
export function getSpecialFusionInputs(targetName, specialRecipes, fusionUnlocks, personas = []) {
  if (!targetName) return null

  const targetKey = Object.keys(specialRecipes || {}).find((name) => normalizeName(name) === normalizeName(targetName))
  const ingredients = targetKey ? specialRecipes[targetKey] : null
  if (!ingredients) return null

  const personaMatch = personas.find((persona) =>
    normalizeName(persona.name) === normalizeName(targetName) ||
    normalizeName(persona.query) === normalizeName(targetName)
  )

  const unlockEntry = fusionUnlocks
    .flatMap((category) => Object.entries(category.conditions || {}))
    .find(([name]) => normalizeName(name) === normalizeName(targetName) || normalizeName(name) === normalizeName(personaMatch?.name || ''))

  return { ingredients, unlockCondition: unlockEntry?.[1] ?? null }
}