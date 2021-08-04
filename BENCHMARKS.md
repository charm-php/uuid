Benchmark Results
=================

These benchmarks can be repeated with the `benchmarks/benchmarks.php` script.

#1: Single UUID v1 generation time
----------------------------------
Measured the time it takes to generate *a single UUID v1*.

> charm/uuid is **faster then ramsey/uuid and similar to the pecl extension** in all benchmarks.

| Library     | Runs | Average Time | Median Time | Fastest   | Slowest   | Included files |
|-------------|------|--------------|-------------|-----------|-----------|----------------|
| charm/uuid  |  500 |   **0.66 ms**|  **0.51 ms**|**0.42 ms**|**7.49 ms**|             11 |
| ramsey/uuid |  500 |     11.09 ms |    10.31 ms |   6.14 ms |  51.24 ms |             68 |
| pecl:uuid   |  500 |     *0.74 ms*|     0.34 ms |   0.20 ms |   5.25 ms |              9 |

#2: Generating 100000 UUID v1
-----------------------------
Measured the time it takes to generate 100 000 UUID v4 with 20 repetitions.

> charm/uuid is **faster then ramsey/uuid and the pecl extension** in all benchmarks.

| Library     | Runs | Average Time | Median Time | Fastest     | Slowest     | Included files |
|-------------|------|--------------|-------------|-------------|-------------|----------------|
| charm/uuid  |   20 | **488.73 ms**|**462.37 ms**|**429.65 ms**|**815.00 ms**|             11 |
| ramsey/uuid |   20 |  2,186.58 ms | 2,207.76 ms | 1,891.50 ms | 2,557.41 ms |             68 |
| pecl:uuid   |   20 |   *528.75 ms*|  *506.95 ms*|   419.32 ms |   766.04 ms |              9 |

#3: Single UUID v4 generation time
----------------------------------
Measured the time it takes to generate *a single UUID v4*.

> charm/uuid is **faster then ramsey/uuid** in all benchmarks, but slower then the pecl extension.

| Library     | Runs | Average Time | Median Time | Fastest   | Slowest   | Included files |
|-------------|------|--------------|-------------|-----------|-----------|----------------|
| charm/uuid  |  500 |   **0.41 ms**|  **0.33 ms**|**0.27 ms**|**4.66 ms**|             11 |
| ramsey/uuid |  500 |      5.05 ms |     4.17 ms |   2.75 ms |  49.59 ms |             63 |
| pecl:uuid   |  500 |      0.13 ms |     0.11 ms |   0.09 ms |   0.70 ms |              9 |

#4: Generating 100000 UUID v4
-----------------------------
Measured the time it takes to generate 100 000 UUID v4 with 20 repetitions.

> charm/uuid is **faster then ramsey/uuid and the pecl extension** in all benchmarks.

| Library     | Runs | Average Time | Median Time | Fastest     | Slowest     | Included files |
|-------------|------|--------------|-------------|-------------|-------------|----------------|
| charm/uuid  |   20 | **592.79 ms**|**575.64 ms**|**489.03 ms**|**825.02 ms**|             11 |
| ramsey/uuid |   20 |    898.88 ms |   909.63 ms |   726.32 ms | 1,055.50 ms |             63 |
| pecl:uuid   |   20 | *1,091.64 ms*|*1,082.32 ms*|  *925.04 ms*|*1,383.86 ms*|              9 |
