<?php

declare(strict_types=1);

namespace App\Infrastructure;

use RdKafka\Conf;
use RdKafka\KafkaConsumer;
use RdKafka\Producer;

final class KafkaFactory
{
    public static function producer(): Producer
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', Config::string('KAFKA_BROKERS', 'kafka:9092'));
        $conf->set('socket.timeout.ms', '10000');

        return new Producer($conf);
    }

    public static function consumer(string $groupId): KafkaConsumer
    {
        $conf = new Conf();
        $conf->set('metadata.broker.list', Config::string('KAFKA_BROKERS', 'kafka:9092'));
        $conf->set('group.id', $groupId);
        $conf->set('enable.auto.commit', 'true');
        $conf->set('auto.offset.reset', 'earliest');

        return new KafkaConsumer($conf);
    }
}
