import yfinance as yf
import sqlite3
import os
from datetime import datetime, timedelta

TICKERS = ['AAPL', 'MSFT', 'GOOGL', 'AMZN', 'NVDA', 'META', 'TSLA']

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
DB_PATH  = os.path.join(BASE_DIR, 'db.sqlite')
DATA_DIR = os.path.join(BASE_DIR, 'data')


def init_db(conn):
    conn.executescript('''
        CREATE TABLE IF NOT EXISTS candles (
            id      INTEGER PRIMARY KEY,
            ticker  TEXT    NOT NULL,
            ts      INTEGER NOT NULL,
            open    REAL,
            high    REAL,
            low     REAL,
            close   REAL,
            volume  INTEGER,
            UNIQUE(ticker, ts)
        );

        CREATE TABLE IF NOT EXISTS last_updated (
            ticker         TEXT PRIMARY KEY,
            last_pull_date TEXT
        );
    ''')
    conn.commit()


def pull_ticker(ticker):
    df = yf.download(ticker, interval='1m', period='2d', progress=False, auto_adjust=True)
    df.columns = [c[0] if isinstance(c, tuple) else c for c in df.columns]
    return df


def upsert_candles(conn, ticker, df):
    rows = [
        (ticker, int(ts.timestamp()), row['Open'], row['High'], row['Low'], row['Close'], int(row['Volume']))
        for ts, row in df.iterrows()
    ]
    conn.executemany(
        'INSERT OR REPLACE INTO candles (ticker, ts, open, high, low, close, volume) VALUES (?,?,?,?,?,?,?)',
        rows,
    )
    conn.commit()


def prune_old_candles(conn):
    cutoff = int((datetime.now() - timedelta(days=7)).timestamp())
    conn.execute('DELETE FROM candles WHERE ts < ?', (cutoff,))
    conn.commit()


def update_last_pull(conn, ticker):
    today = datetime.now().strftime('%Y-%m-%d')
    conn.execute(
        'INSERT OR REPLACE INTO last_updated (ticker, last_pull_date) VALUES (?, ?)',
        (ticker, today),
    )
    conn.commit()


def write_json_cache(ticker, df):
    import json
    out = []
    for ts, row in df.iterrows():
        out.append({
            'ts':     int(ts.timestamp()),
            'open':   round(float(row['Open']),  4),
            'high':   round(float(row['High']),  4),
            'low':    round(float(row['Low']),   4),
            'close':  round(float(row['Close']), 4),
            'volume': int(row['Volume']),
        })
    path = os.path.join(DATA_DIR, f'{ticker}_1m.json')
    with open(path, 'w') as f:
        json.dump(out, f)


def main():
    os.makedirs(DATA_DIR, exist_ok=True)
    conn = sqlite3.connect(DB_PATH)
    init_db(conn)

    for ticker in TICKERS:
        print(f'Pulling {ticker}...', end=' ', flush=True)
        df = pull_ticker(ticker)
        if df.empty:
            print('no data returned, skipping')
            continue
        upsert_candles(conn, ticker, df)
        update_last_pull(conn, ticker)
        write_json_cache(ticker, df)
        print(f'{len(df)} candles')

    prune_old_candles(conn)
    conn.close()
    print('Done.')


if __name__ == '__main__':
    main()
