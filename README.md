A very fast UUID and snowflake generator
========================================

After looking at other libraries, they seem very complex for the most common use case:
generating UUIDs. Now, UUIDs are quite complex in themselves, and it is easy to make mistakes
so that the UUID is not truly unique. That's why I feel the library should be as simple as
possible, so that it is easy to review.

Features:

 * UUID v4 using cryptographically strong randomness

 * UUID v1

 * COMB UUID v4 - All new UUIDs will be larger than the previous. The UUID is built from
   a 60 bit timestamp, a 14 bit sequence number and a 48 bit machine id. In this implementation
   the UUID will validate as a proper UUID v4, which means that any picky third party software
   which validates the UUID will treat the UUID as a compliant version 4 UUID.

 * Snowflake UID - 64 bit increasing ID suitable for database primary keys

 * Instaflake UID - a variation of the Snowflake UID

 * Sonyflake UID - another variation of Snowflake

This library provides two classes and has no dependencies and performs very well. The UUID v4
is cryptographic random (via `random_bytes()`), while UUID v1 tries to find the MAC address
or another unique machine ID. To reduce the risk of collisions, the process id is used to
to reduce the chance of a single machine generating two identical IDs.


Are they unique?
----------------

A lot of effort was put into defining the UUID standard, and a they are considered to be unique
for all practical purposes.

UUID v4 is the simplest to use, but they require 16 bytes of storage each. They need no 
configuration, and they can be generated very quickly. If all companies in the world generate 
a total of 1 billion UIDs every second, the first collision is expected to happen after about 
85 years. After those 85 years, most of these UUIDs will have vanished anyway.

Snowflake ID are only 8 bytes (64 bits) and can safely be be used inside your own databases. 
They were invented by Twitter and is used to generate unique IDs for tweets, users, things, 
images and so on. To ensure uniqueness, you may configure a unique machineId, but the library
will automatically pick an available machine id from the operating system if you don't.

UUID v1 is a "coordinate" using the clock and a unique machine id derived from the mac address
or another unique 48 bit machine identifier. They are also automatically configured in this 
library.


Warning
-------

Please run some tests in your environment before using this library. Particularly, ensure
that the server has enough entropy available if you're using UUID version 4. Entropy is random
numbers generated from network noise and other sources.

UUIDs version 4 are considered production ready, but the other IDs need some more scrutiny
before we can declare them production ready. Please let me know if you spot any problems.


Quick Start
-----------

If you're not using a service container, the quickest way to begin is by using the function
`Charm\Id::make()`. The returned value will be a unique ID that nobody else have ever seen
before.

```php
/**
 * UUID v4
 *
 * An ID that can be shared with others, and you should never see a collision.
 */
$uniqueId = Charm\Id::make();
// 47e3c427-3f82-4dc7-a6ca-c83561a9cdfb

/**
 * Snowflake, by Twitter
 *
 * A 64 bit integer which can be considered unique within your organization, built from
 * a timestamp, a machine id and a sequence number.
 */
$snowflakeId = Charm\Id::snowflake();
// 262805082062461697
```

Service Object API
------------------

```php
use Charm\Util\IdFactory;

// Configure the service provider object
$idGenerator = new IdFactory(IdFactory::TYPE_UUID_V1, [
    /**
     * If you specify a machine id here, no effort is needed to retrieve a machine id.
     * The value should be globally unique for UUID V1, or unique for the organization
     * for snowflake/instaflake/sonyflake type IDs.
     */
    'machineId' => null,

    /**
     * The sequence number is a number which is supposed to ensure that we don't generate
     * two IDs on the same computer within the same time interval. The default value is derived
     * from `getmypid()` which should cause diffent workers to start on a different sequence
     * number.
     */
    'initialSequenceNumber' => null,

    /**
     * The epoch for the snowflake and derivatives ID generators is a unix
     * timestamp.
     */
    'epoch' => strtotime('2019-01-01 00:00:00'),

    /**
     * Allow fetching the computers mac address for machine id?
     */
    'allowMacAddress' => true,

    /**
     * Allow using the Kubernetes hostname UID part for machine id?
     */
    'allowKubernetesId' => true,

    /**
     * Allow unique machine ID from /var/lib/dbus/machine-id or on Windows, the registry entry in 
     * 'HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Cryptography\MachineGuid'?
     */
    'allowMachineId' => true,

    /**
     * You can provide a custom function that provides a unique ID for the machine. The function
     * must return a positive integer or NULL.
     */
    'customMachineIdFunction' => null,
]);

// Generate a new ID. The type of ID is determined by the `$type` specified in the constructor.
$idGenerator(); // c85fb57a-f391-11eb-bb00-0242ee781401
```

Static API
----------

```php
use Charm\Id;
use Charm\Util\IdFactory;

// Optionally configure the library before generating any IDs.
Id::configure(IdFactory::TYPE_UUID_V4, [ /* options, see above */);

// Generate the default ID type
Id::make();
// 47e3c427-3f82-4dc7-a6ca-c83561a9cdfb

// Or use any of the other factory methods to create a particular type of ID

Charm\Id::uuid1();
// c85fb57a-f391-11eb-bb00-0242ee781401

Charm\Id::uuid4();
// 47e3c427-3f82-4dc7-a6ca-c83561a9cdfb

Charm\Id::comb();
// 061089ba-be18-475b-90ed-0242ee781401

Charm\Id::snowflake();
// 262805082062461697

Charm\Id::instaflake();
// 262805082067699458

Charm\Id::sonyflake();
// 33064438777189377
```


Configuration:
--------------

Configuration is done via the `IdFactory` constructor, or the `Id::configure()` method.
For up-to-date configuration options, see the `IdFactory` source file.


Comparison:
-----------

* UUID v1 provides an 128 bit RFC4122 compliant UUID version 1 variant 1. It is designed to
  be globally unique for any datacenter and computer, always. It uses 48 bits to uniquely 
  identify the computer it runs on, and a 60 bit timestamp in 100 nanosecond increments 
  since 15th October 1582. The probability for a collision is extremely small.

* UUID v4 provides an 128 bit RFC4122 compliant UUID v4 variant 1 (also aka GUID). It is 
  built from a very large 122 bit random number. The generator uses a random_bytes() which
  consumes random numbers which are considered cryptographically secure.

* COMB UUID camouflages as a 128 bit RFC4122 compliant UUID v4, but it is built using the same
  principles as UUID v1: 60 bit timestamp, 14 bit sequence ID and 48 bit machine ID in a
  sorted order. 

* Snowflake is a 64 bit integer, which is suitable for storing in databases and is able to
  guarantee unique IDs across data centers and servers - provided that the machine ID is
  unique. 

* Instaflake and Sonyflake are variations of Snowflake, with different priorities with regard
  to timestamp resolution and number of machines.


Snowflake and variants are usually sufficient for any internal ID scheme up to Twitter or
Instagram scale. UUIDs are by many considered to be too large to be used extensively 
internally in a database - but are very useful to generate unique IDs for APIs or for allowing
external clients to generate IDs offline.
