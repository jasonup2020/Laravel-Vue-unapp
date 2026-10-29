<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Base artisan command with full bilingual help/带完整双语帮助的 artisan 命令基类
 *
 * All custom skeleton commands extend this base. It:
 *  1. Registers a global --show-full-help option;
 *  2. When that option is passed, renders a structured bilingual help page
 *     (usage / arguments / options / examples) generated dynamically from the
 *     command signature via getDefinition().
 *  本工程所有自定义 artisan 命令均继承本基类。功能：
 *   1. 全局注册 --show-full-help 选项；
 *   2. 传该选项时输出结构化双语帮助（用法/参数/选项/示例），内容从命令 signature
 *      通过 getDefinition() 动态生成，新增参数自动跟随。
 */
abstract class BaseCommand extends Command
{
    /**
     * Configure the command/配置命令
     *
     * Registers the global --show-full-help option after parent configure.
     * 在父类 configure 之后追加全局 --show-full-help 选项。
     */
    protected function configure(): void
    {
        parent::configure();
        $this->addOption(
            'show-full-help',
            null,
            InputOption::VALUE_NONE,
            'Show full bilingual help/显示完整双语帮助'
        );
    }

    /**
     * Execute the command/执行命令
     *
     * Intercept --show-full-help before running the real handle().
     * 在真正执行 handle() 之前拦截 --show-full-help。
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasOption('show-full-help') && $input->getOption('show-full-help')) {
            $this->showFullHelp();

            return self::SUCCESS;
        }

        return parent::execute($input, $output);
    }

    /**
     * Run the command/运行命令
     *
     * Intercept --show-full-help at the outermost layer (before Symfony validates
     * required arguments) so the user can view help without supplying <name>.
     * 在最外层拦截 --show-full-help（早于 Symfony 必填参数校验），
     * 这样用户无需提供 <name> 即可查看完整帮助。
     */
    public function run(InputInterface $input, OutputInterface $output): int
    {
        if ($input->hasParameterOption('--show-full-help', true)) {
            // Force definition to be built so getSynopsis()/getDefinition() work/
            // 强制构建 definition，保证 getSynopsis()/getDefinition() 可用
            $this->getDefinition();
            // Bind output so line()/info()/comment() work before parent::run()/
            // 提前绑定 output，使 line()/info()/comment() 在 parent::run() 前可用
            $this->output = $output;
            $this->showFullHelp();

            return self::SUCCESS;
        }

        return parent::run($input, $output);
    }

    /**
     * Render the full bilingual help page/渲染完整双语帮助页
     *
     * Reads arguments/options dynamically from getDefinition() so new params
     * declared in $signature show up automatically.
     * 通过 getDefinition() 动态读取参数与选项，$signature 新增项自动跟随。
     */
    protected function showFullHelp(): void
    {
        $name = (string) $this->getName();
        $desc = (string) $this->getDescription();
        // Use native (command-only) definition to avoid leaking global args/options
        // like "command", "-h", "--quiet", "--env".
        // 使用命令原生 definition，避免混入 "command"、"-h"、"--quiet"、"--env" 等全局项。
        $definition = $this->getNativeDefinition();
        $arguments = $definition->getArguments();
        $options = $definition->getOptions();

        $line = str_repeat('=', 72);
        $this->line($line);
        $this->line(" Command: {$name}");
        if ($desc !== '') {
            $this->line(" {$desc}");
        }
        $this->line($line);
        $this->line('');

        // Usage/用法
        $this->line('<comment>USAGE / 用法:</comment>');
        $this->line('  php artisan ' . $this->getSynopsis());
        $this->line('');

        // Arguments/参数
        if ($arguments !== []) {
            $this->line('<comment>ARGUMENTS / 参数:</comment>');
            foreach ($arguments as $arg) {
                $req = $arg->isRequired() ? 'required/必填' : 'optional/可选';
                $defaultVal = $arg->getDefault();
                $default = ($defaultVal !== null && $defaultVal !== [])
                    ? ' [default: ' . var_export($defaultVal, true) . ']'
                    : '';
                $this->line(sprintf(
                    '  <info>%-22s</info> %s (%s%s)',
                    $arg->getName(),
                    $arg->getDescription() ?: '-',
                    $req,
                    $default
                ));
            }
            $this->line('');
        }

        // Options/选项
        if ($options !== []) {
            $this->line('<comment>OPTIONS / 选项:</comment>');
            foreach ($options as $opt) {
                $shortcut = $opt->getShortcut() ? '-' . $opt->getShortcut() . ', ' : '    ';
                $namePart = '--' . $opt->getName();
                $nameWithShortcut = $shortcut . $namePart;

                $mode = $opt->isValueRequired()
                    ? '=VALUE/需带值'
                    : ($opt->isValueOptional() ? '[=VALUE]/可选值' : 'flag/开关');

                $defaultVal = $opt->getDefault();
                $default = ($defaultVal !== null && $defaultVal !== [])
                    ? ' [default: ' . var_export($defaultVal, true) . ']'
                    : '';

                $this->line(sprintf(
                    '  <info>%-26s</info> %s (%s%s)',
                    $nameWithShortcut,
                    $opt->getDescription() ?: '-',
                    $mode,
                    $default
                ));
            }
            $this->line('');
        }

        // Examples/示例
        $examples = $this->examples();
        if ($examples !== []) {
            $this->line('<comment>EXAMPLES / 示例:</comment>');
            foreach ($examples as $ex) {
                $this->line('  $ php artisan ' . $ex['cmd']);
                if (!empty($ex['desc'])) {
                    $this->line('    ' . $ex['desc']);
                }
            }
            $this->line('');
        }
    }

    /**
     * Command-specific examples/命令专属示例
     *
     * Child classes override to return [['cmd' => '...', 'desc' => 'English/中文'], ...].
     * 子类覆写返回示例数组。
     *
     * @return array<int,array{cmd:string,desc:string}>
     */
    protected function examples(): array
    {
        return [];
    }
}
