export type DraftMode = 'auto' | 'arrival' | 'manual' | 'random';

export type CaptainMode = 'arrival' | 'manual' | 'random';

export type DraftPreviewSide = 'A' | 'B';

export type DraftPreviewEntry = {
    id: number;
    name: string;
    arrival_order: number;
    jersey_number: number | null;
    preferred_position: string | null;
    scout_role: string | null;
    auto_draft_rating: number;
    is_guest: boolean;
};

export type DraftPreviewPlayer<TEntry extends DraftPreviewEntry> = TEntry & {
    is_captain: boolean;
};

export type DraftPreviewResult<TEntry extends DraftPreviewEntry> = {
    teams: Record<DraftPreviewSide, DraftPreviewPlayer<TEntry>[]>;
    unassigned: TEntry[];
    counts: Record<DraftPreviewSide, number> & { unassigned: number };
};

type DraftPreviewOptions<TEntry extends DraftPreviewEntry> = {
    entries: TEntry[];
    sessionId: number;
    currentGameNumber: number;
    mode: DraftMode;
    captainMode: CaptainMode;
    assignments?: Record<number, DraftPreviewSide>;
    captains?: Partial<Record<DraftPreviewSide, number | null>>;
};

type DraftTeams<TEntry extends DraftPreviewEntry> = Record<DraftPreviewSide, TEntry[]>;

export async function buildDraftPreview<TEntry extends DraftPreviewEntry>(
    options: DraftPreviewOptions<TEntry>,
): Promise<DraftPreviewResult<TEntry>> {
    const teams =
        options.mode === 'manual'
            ? buildManualTeams(options.entries, options.assignments ?? {})
            : await buildAutomaticTeams(options);
    const captainIds = await resolveCaptainIds({
        ...options,
        teams,
    });

    return {
        teams: {
            A: orderTeam(teams.A, captainIds.A),
            B: orderTeam(teams.B, captainIds.B),
        },
        unassigned:
            options.mode === 'manual'
                ? options.entries
                      .filter((entry) => !options.assignments?.[entry.id])
                      .sort(byArrival)
                : [],
        counts: {
            A: teams.A.length,
            B: teams.B.length,
            unassigned:
                options.mode === 'manual'
                    ? options.entries.filter(
                          (entry) => !options.assignments?.[entry.id],
                      ).length
                    : 0,
        },
    };
}

async function buildAutomaticTeams<TEntry extends DraftPreviewEntry>(
    options: DraftPreviewOptions<TEntry>,
): Promise<DraftTeams<TEntry>> {
    switch (options.mode) {
        case 'arrival':
            return arrivalTeams(options.entries);
        case 'random':
            return randomTeams(
                options.entries,
                `session:${options.sessionId}:${options.currentGameNumber}:${options.mode}`,
            );
        case 'auto':
            return autoTeams(options.entries);
        default:
            return { A: [], B: [] };
    }
}

function buildManualTeams<TEntry extends DraftPreviewEntry>(
    entries: TEntry[],
    assignments: Record<number, DraftPreviewSide>,
): DraftTeams<TEntry> {
    return {
        A: entries.filter((entry) => assignments[entry.id] === 'A'),
        B: entries.filter((entry) => assignments[entry.id] === 'B'),
    };
}

function arrivalTeams<TEntry extends DraftPreviewEntry>(
    entries: TEntry[],
): DraftTeams<TEntry> {
    const ordered = [...entries].sort(byArrival);

    return {
        A: ordered.slice(0, 5),
        B: ordered.slice(5, 10),
    };
}

async function randomTeams<TEntry extends DraftPreviewEntry>(
    entries: TEntry[],
    seed: string,
): Promise<DraftTeams<TEntry>> {
    const weighted = await Promise.all(
        entries.map(async (entry) => ({
            entry,
            weight: await deterministicEntryOrderValue(`${seed}:draft`, entry.id),
        })),
    );
    const ordered = weighted
        .sort((left, right) => {
            if (left.weight !== right.weight) {
                return left.weight - right.weight;
            }

            return byArrival(left.entry, right.entry);
        })
        .map(({ entry }) => entry);

    return {
        A: ordered.slice(0, 5),
        B: ordered.slice(5, 10),
    };
}

function autoTeams<TEntry extends DraftPreviewEntry>(
    entries: TEntry[],
): DraftTeams<TEntry> {
    const ordered = [...entries].sort((left, right) => {
        if (right.auto_draft_rating !== left.auto_draft_rating) {
            return right.auto_draft_rating - left.auto_draft_rating;
        }

        return byArrival(left, right);
    });
    const teamA: TEntry[] = [];
    const teamB: TEntry[] = [];
    let scoreA = 0;
    let scoreB = 0;

    for (const candidate of ordered) {
        const canA = teamA.length < 5;
        const canB = teamB.length < 5;

        if (!canA && !canB) {
            break;
        }

        if (canA && !canB) {
            teamA.push(candidate);
            scoreA += candidate.auto_draft_rating;

            continue;
        }

        if (canB && !canA) {
            teamB.push(candidate);
            scoreB += candidate.auto_draft_rating;

            continue;
        }

        const assignToA =
            candidate.scout_role === 'Anotador'
                ? scorerCount(teamA) === scorerCount(teamB)
                    ? scoreA <= scoreB
                    : scorerCount(teamA) <= scorerCount(teamB)
                : scoreA <= scoreB;

        if (assignToA) {
            teamA.push(candidate);
            scoreA += candidate.auto_draft_rating;

            continue;
        }

        teamB.push(candidate);
        scoreB += candidate.auto_draft_rating;
    }

    return { A: teamA, B: teamB };
}

async function resolveCaptainIds<TEntry extends DraftPreviewEntry>(
    options: DraftPreviewOptions<TEntry> & { teams: DraftTeams<TEntry> },
): Promise<Record<DraftPreviewSide, number | null>> {
    switch (options.captainMode) {
        case 'arrival':
            return {
                A: options.teams.A.length > 0 ? options.teams.A.sort(byArrival)[0].id : null,
                B: options.teams.B.length > 0 ? options.teams.B.sort(byArrival)[0].id : null,
            };
        case 'random':
            return {
                A: await randomCaptainId(
                    options.teams.A,
                    `session:${options.sessionId}:${options.currentGameNumber}:${options.mode}:captain:A`,
                ),
                B: await randomCaptainId(
                    options.teams.B,
                    `session:${options.sessionId}:${options.currentGameNumber}:${options.mode}:captain:B`,
                ),
            };
        case 'manual':
            return {
                A: validManualCaptainId(options.teams.A, options.captains?.A),
                B: validManualCaptainId(options.teams.B, options.captains?.B),
            };
        default:
            return { A: null, B: null };
    }
}

async function randomCaptainId<TEntry extends DraftPreviewEntry>(
    team: TEntry[],
    seed: string,
): Promise<number | null> {
    if (team.length === 0) {
        return null;
    }

    const weighted = await Promise.all(
        team.map(async (entry) => ({
            id: entry.id,
            arrival_order: entry.arrival_order,
            weight: await deterministicEntryOrderValue(seed, entry.id),
        })),
    );

    return weighted.sort((left, right) => {
        if (left.weight !== right.weight) {
            return left.weight - right.weight;
        }

        return left.arrival_order - right.arrival_order;
    })[0]?.id ?? null;
}

function validManualCaptainId<TEntry extends DraftPreviewEntry>(
    team: TEntry[],
    captainId: number | null | undefined,
): number | null {
    if (!captainId) {
        return null;
    }

    return team.some((entry) => entry.id === captainId) ? captainId : null;
}

function orderTeam<TEntry extends DraftPreviewEntry>(
    team: TEntry[],
    captainId: number | null,
): DraftPreviewPlayer<TEntry>[] {
    if (!captainId || !team.some((entry) => entry.id === captainId)) {
        return [...team]
            .sort(byName)
            .map((entry) => ({ ...entry, is_captain: false }));
    }

    const captain = team.find((entry) => entry.id === captainId);
    const rest = team
        .filter((entry) => entry.id !== captainId)
        .sort(byName)
        .map((entry) => ({ ...entry, is_captain: false }));

    return [
        { ...(captain as TEntry), is_captain: true },
        ...rest,
    ];
}

function scorerCount<TEntry extends DraftPreviewEntry>(team: TEntry[]): number {
    return team.filter((entry) => entry.scout_role === 'Anotador').length;
}

function byArrival<TEntry extends DraftPreviewEntry>(
    left: TEntry,
    right: TEntry,
): number {
    return left.arrival_order - right.arrival_order;
}

function byName<TEntry extends DraftPreviewEntry>(left: TEntry, right: TEntry): number {
    return left.name.localeCompare(right.name, 'es', {
        sensitivity: 'base',
    });
}

async function deterministicEntryOrderValue(seed: string, entryId: number): Promise<number> {
    const hashInput = `${seed}:${entryId}`;
    const subtle = globalThis.crypto?.subtle;

    if (!subtle) {
        return fallbackHash(hashInput);
    }

    const bytes = new Uint8Array(
        await subtle.digest('SHA-256', new TextEncoder().encode(hashInput)),
    );
    const hex = Array.from(bytes.slice(0, 6))
        .map((byte) => byte.toString(16).padStart(2, '0'))
        .join('');

    return Number.parseInt(hex, 16);
}

function fallbackHash(input: string): number {
    let hash = 0;

    for (const character of input) {
        hash = (hash * 31 + character.charCodeAt(0)) % 281_474_976_710_655;
    }

    return hash;
}
