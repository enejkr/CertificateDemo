<?php

class RateLimiter
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function check(
        string $key,
        int $maxRequests,
        int $windowSeconds
    ): array {
        $now = time();

        try {
            $this->pdo->beginTransaction();

            $stmt = $this->pdo->prepare(
                'SELECT window_start, request_count
                 FROM rate_limits
                 WHERE rate_key = :rate_key
                 FOR UPDATE'
            );

            $stmt->execute([
                'rate_key' => $key
            ]);

            $row = $stmt->fetch();

            // ni se nobenega zapisa.
            if ($row === false) {
                $stmt = $this->pdo->prepare(
                    'INSERT INTO rate_limits
                        (rate_key, window_start, request_count)
                     VALUES
                        (:rate_key, :window_start, 1)'
                );

                $stmt->execute([
                    'rate_key' => $key,
                    'window_start' => $now
                ]);

                $this->pdo->commit();

                return [
                    'allowed' => true,
                    'retry_after' => 0
                ];
            }

            $windowStart = (int) $row['window_start'];
            $requestCount = (int) $row['request_count'];

            $elapsed = $now - $windowStart;

            // end of time limit
            if ($elapsed >= $windowSeconds) {
                $stmt = $this->pdo->prepare(
                    'UPDATE rate_limits
                     SET window_start = :window_start,
                         request_count = 1
                     WHERE rate_key = :rate_key'
                );

                $stmt->execute([
                    'window_start' => $now,
                    'rate_key' => $key
                ]);

                $this->pdo->commit();

                return [
                    'allowed' => true,
                    'retry_after' => 0
                ];
            }

            $retryAfter = $windowSeconds - $elapsed;

            // Limit je dosežen.
            if ($requestCount >= $maxRequests) {
                $this->pdo->commit();

                return [
                    'allowed' => false,
                    'retry_after' => $retryAfter
                ];
            }
            // counter ++
            $stmt = $this->pdo->prepare(
                'UPDATE rate_limits
                 SET request_count = request_count + 1
                 WHERE rate_key = :rate_key'
            );

            $stmt->execute([
                'rate_key' => $key
            ]);

            $this->pdo->commit();

            return [
                'allowed' => true,
                'retry_after' => $retryAfter
            ];

        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }

            throw $e;
        }
    }
}