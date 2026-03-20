<?php

declare(strict_types=1);

namespace Spawners;

use pocketmine\plugin\PluginBase;
use pocketmine\player\Player;
use pocketmine\block\VanillaBlocks;
use pocketmine\item\CreativeInventory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\Location;
use pocketmine\world\Position;
use pocketmine\world\World;
use pocketmine\scheduler\ClosureTask;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\event\block\BlockPlaceEvent;

class Main extends PluginBase implements Listener {

    private array $spawners = [];

    public function onEnable(): void {
        $this->saveDefaultConfig();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->registerCreativeSpawners();

        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void {
            $this->tickSpawners();
        }), $this->getConfig()->get("spawn-delay"));
    }

    private function registerCreativeSpawners(): void {
        $types = $this->getConfig()->get("spawner-types");

        foreach ($types as $type => $name) {

            // ✅ FIXED LINE HERE
            $item = VanillaBlocks::MONSTER_SPAWNER()->asItem();

            $item->setCustomName("§r§a" . ucfirst($type) . " Spawner");

            $nbt = new CompoundTag();
            $nbt->setString("spawner_type", $type);
            $item->setNamedTag($nbt);

            CreativeInventory::getInstance()->add($item);
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool {
        if ($command->getName() === "givespawner") {
            if (!$sender instanceof Player) return true;

            if (!isset($args[0])) {
                $sender->sendMessage("§cUsage: /givespawner <type>");
                return true;
            }

            $type = strtolower($args[0]);
            $types = $this->getConfig()->get("spawner-types");

            if (!isset($types[$type])) {
                $sender->sendMessage("§cInvalid type.");
                return true;
            }

            // ✅ FIXED HERE TOO
            $item = VanillaBlocks::MONSTER_SPAWNER()->asItem();

            $item->setCustomName("§r§a" . ucfirst($type) . " Spawner");

            $nbt = new CompoundTag();
            $nbt->setString("spawner_type", $type);
            $item->setNamedTag($nbt);

            $sender->getInventory()->addItem($item);
            $sender->sendMessage("§aGiven " . ucfirst($type) . " Spawner!");
            return true;
        }

        return false;
    }

    public function onBlockPlace(BlockPlaceEvent $event): void {
        $item = $event->getItem();
        $nbt = $item->getNamedTag();

        if ($nbt->getTag("spawner_type") !== null) {
            $pos = $event->getBlock()->getPosition();

            $this->spawners[] = [
                "x" => $pos->getX(),
                "y" => $pos->getY(),
                "z" => $pos->getZ(),
                "world" => $pos->getWorld()->getFolderName(),
                "type" => $nbt->getString("spawner_type")
            ];
        }
    }

    private function tickSpawners(): void {
        foreach ($this->spawners as $spawner) {
            $world = $this->getServer()->getWorldManager()->getWorldByName($spawner["world"]);
            if (!$world instanceof World) continue;

            $pos = new Position($spawner["x"], $spawner["y"] + 1, $spawner["z"], $world);

            $entity = $this->createEntity($spawner["type"], $pos);
            if ($entity !== null) {
                $entity->spawnToAll();
            }
        }
    }

    private function createEntity(string $type, Position $pos) {
        $nbt = new CompoundTag();

        $location = new Location(
            $pos->getX(),
            $pos->getY(),
            $pos->getZ(),
            $pos->getWorld(),
            0,
            0
        );

        try {
            return EntityFactory::getInstance()->createFromData(
                ucfirst($type),
                $location,
                $nbt
            );
        } catch (\Throwable $e) {
            return null;
        }
    }
}
