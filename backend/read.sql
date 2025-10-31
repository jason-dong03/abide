CREATE EXTENSION IF NOT EXISTS citext;

-- enumerated types
DO $$ BEGIN
  CREATE TYPE friend_status AS ENUM ('pending','accepted','declined','blocked');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

DO $$ BEGIN
  CREATE TYPE checkin_frequency AS ENUM ('daily','weekly','monthly');
EXCEPTION WHEN duplicate_object THEN NULL; END $$;

-- drop tables (order doesn't matter due to CASCADE)
DROP TABLE IF EXISTS checkins CASCADE;
DROP TABLE IF EXISTS challenge_participants CASCADE;
DROP TABLE IF EXISTS challenges CASCADE;
DROP TABLE IF EXISTS user_badges CASCADE;
DROP TABLE IF EXISTS badges CASCADE;
DROP TABLE IF EXISTS login_events CASCADE;
DROP TABLE IF EXISTS users CASCADE;
DROP TABLE IF EXISTS friends CASCADE;

-- USER + AUTH
CREATE TABLE users (
    user_id               SERIAL PRIMARY KEY,
    first_name            TEXT NOT NULL,
    last_name             TEXT NOT NULL,
    email                 CITEXT UNIQUE NOT NULL,
    phone_number          TEXT UNIQUE,
    username              TEXT NOT NULL,
    password_hash         TEXT NOT NULL,

    login_days_count      INTEGER NOT NULL DEFAULT 0,
    last_login_at         TIMESTAMPTZ,
    current_login_at      TIMESTAMPTZ,
    login_streak_current  INTEGER NOT NULL DEFAULT 0,
    login_streak_longest  INTEGER NOT NULL DEFAULT 0,

    created_at            TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at            TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- login events to track streak
CREATE TABLE login_events (
  login_event_id SERIAL PRIMARY KEY,
  user_id        INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
  logged_in_at   TIMESTAMPTZ NOT NULL DEFAULT now(),
  logged_in_date DATE NOT NULL
);

-- One event per user per calendar day (prevents inflated counts)
CREATE UNIQUE INDEX uq_login_events_user_day
  ON login_events (user_id, logged_in_date);

-- BADGES
CREATE TABLE IF NOT EXISTS badges (
    badge_id    SERIAL PRIMARY KEY,
    code        TEXT UNIQUE NOT NULL,
    name        TEXT NOT NULL,
    description TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE IF NOT EXISTS user_badges (
    user_id     INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    badge_id     INTEGER NOT NULL REFERENCES badges(badge_id) ON DELETE CASCADE,
    awarded_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    PRIMARY KEY (user_id, badge_id)
);



-- CHALLENGES
CREATE TABLE IF NOT EXISTS challenges (
    challenge_id   SERIAL PRIMARY KEY,
    creator_id     INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    title          TEXT NOT NULL,
    description    TEXT,
    start_date     DATE NOT NULL,
    end_date       DATE NOT NULL,
    frequency      checkin_frequency NOT NULL,
    goal_unit      TEXT NOT NULL CHECK (goal_unit IN ('pages', 'chapters')),
    target_amount  INTEGER NOT NULL CHECK (target_amount > 0),
    is_private     BOOLEAN NOT NULL DEFAULT FALSE,
    invite_code    TEXT UNIQUE,
    created_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    CHECK (end_date >= start_date)
);

-- PARTICIPANTS
CREATE TABLE IF NOT EXISTS challenge_participants(
    id SERIAL PRIMARY KEY,
    challenge_id   INTEGER NOT NULL REFERENCES challenges(challenge_id) ON DELETE CASCADE,
    user_id        INTEGER NOT NULL REFERENCES users(user_id) ON DELETE CASCADE,
    joined_at      TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (challenge_id, user_id)
);

-- CHECKINS
CREATE TABLE IF NOT EXISTS checkins (
    checkin_id     SERIAL PRIMARY KEY, 
    participant_id INTEGER NOT NULL REFERENCES challenge_participants(id) ON DELETE CASCADE,
    checkin_at     TIMESTAMPTZ NOT NULL DEFAULT now(),
    amount_done    INTEGER NOT NULL CHECK (amount_done >= 0) -- numeric progress amount
);
CREATE TABLE friends (
    user_id INTEGER REFERENCES users(user_id) ON DELETE CASCADE,
    friend_id INTEGER REFERENCES users(user_id) ON DELETE CASCADE,
    PRIMARY KEY (user_id, friend_id)
);
