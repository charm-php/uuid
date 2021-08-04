Benchmark Results
=================

These benchmarks can be repeated with the `benchmarks/benchmarks` command.

#1: Single UUID v1 generation time
----------------------------------
Measured the time it takes to generate *a single UUID v1*.

> ⭐charm/uuid is **faster then ramsey/uuid and similar to the pecl extension** in all benchmarks.

| Library     | Runs | Average Time | Median Time | Fastest | Slowest  | Included files |
|-------------|------|--------------|-------------|---------|----------|----------------|
| charm/uuid  |  500 |      0.75 ms |     0.66 ms | 0.44 ms |  4.33 ms |             11 |
| ramsey/uuid |  500 |     10.05 ms |     9.16 ms | 6.13 ms | 40.56 ms |             68 |
| pecl:uuid   |  500 |      0.51 ms |     0.37 ms | 0.22 ms | 14.12 ms |              9 |


#2: Generating 100000 UUID v1
-----------------------------
Measured the time it takes to generate 100 000 UUID v4 with 20 repetitions.

> ⭐charm/uuid is **faster then ramsey/uuid and the pecl extension** in all benchmarks.

| Library     | Runs | Average Time | Median Time | Fastest     | Slowest     | Included files |
|-------------|------|--------------|-------------|-------------|-------------|----------------|
| charm/uuid  |   20 |    479.41 ms |   473.52 ms |   427.90 ms |   627.47 ms |             11 |
| ramsey/uuid |   20 |  1,944.46 ms | 1,940.73 ms | 1,801.55 ms | 2,206.63 ms |             68 |
| pecl:uuid   |   20 |    447.86 ms |   439.36 ms |   395.25 ms |   551.61 ms |              9 |


#3: Single UUID v4 generation time
----------------------------------
Measured the time it takes to generate *a single UUID v4*.

> ⭐charm/uuid is **faster then ramsey/uuid** in all benchmarks, but slower then the pecl extension.

| Library     | Runs | Average Time | Median Time | Fastest | Slowest  | Included files |
|-------------|------|--------------|-------------|---------|----------|----------------|
| charm/uuid  |  500 |      0.50 ms |     0.44 ms | 0.29 ms |  8.22 ms |             11 |
| ramsey/uuid |  500 |      4.34 ms |     4.04 ms | 2.86 ms | 10.36 ms |             63 |
| pecl:uuid   |  500 |      0.16 ms |     0.14 ms | 0.09 ms |  0.42 ms |              9 |


#4: Generating 100000 UUID v4
-----------------------------
Measured the time it takes to generate 100 000 UUID v4 with 20 repetitions.

> ⭐charm/uuid is **faster then ramsey/uuid and the pecl extension** in all benchmarks.

| Library     | Runs | Average Time | Median Time | Fastest   | Slowest     | Included files |
|-------------|------|--------------|-------------|-----------|-------------|----------------|
| charm/uuid  |   20 |    495.17 ms |   491.03 ms | 450.88 ms |   558.72 ms |             11 |
| ramsey/uuid |   20 |    779.63 ms |   762.36 ms | 689.43 ms |   895.50 ms |             63 |
| pecl:uuid   |   20 |    963.82 ms |   961.94 ms | 891.60 ms | 1,090.42 ms |              9 |

