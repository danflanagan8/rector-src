<?php

declare(strict_types=1);

namespace Rector\Core\NodeManipulator;

use PhpParser\Node;
use PhpParser\Node\Stmt;
use PHPStan\Analyser\Scope;
use PHPStan\Node\UnreachableStatementNode;
use Rector\Core\NodeAnalyzer\ScopeAnalyzer;
use Rector\Core\NodeAnalyzer\UnreachableStmtAnalyzer;
use Rector\Core\PhpParser\Node\BetterNodeFinder;
use Rector\NodeTypeResolver\Node\AttributeKey;

final class UnreachableStmtScopeManipulator
{
    public function __construct(
        private readonly ScopeAnalyzer $scopeAnalyzer,
        private readonly BetterNodeFinder $betterNodeFinder,
        private readonly UnreachableStmtAnalyzer $unreachableStmtAnalyzer
    ) {
    }

    public function initUnreachableStmtScope(Node $node): void
    {
        if ($this->shouldSkip($node)) {
            return;
        }

        $unreachableStmt = $this->unreachableStmtAnalyzer->resolveUnreachableStmtFromNode($node);
        if (! $unreachableStmt instanceof Stmt) {
            return;
        }

        /**
         * when :
         *     - current Stmt or previous Stmt is unreachable
         *
         * then:
         *     - fill Scope of Parent Stmt
         */
        $this->fillScopeFromParent($node);
    }

    private function shouldSkip(Node $node): bool
    {
        if ($node instanceof Stmt) {
            return true;
        }

        if (! $this->scopeAnalyzer->hasScope($node)) {
            return true;
        }

        $currentStmt = $this->betterNodeFinder->resolveCurrentStatement($node);
        return ! $currentStmt instanceof Stmt;
    }

    private function fillScopeFromParent(Node $node): ?Scope
    {
        $parentNode = $node->getAttribute(AttributeKey::PARENT_NODE);
        while ($parentNode instanceof Stmt) {
            if ($parentNode instanceof UnreachableStatementNode) {
                $parentNode = $parentNode->getAttribute(AttributeKey::PARENT_NODE);
                continue;
            }

            $scope = $parentNode->getAttribute(AttributeKey::SCOPE);
            if (! $scope instanceof Scope) {
                $parentNode = $parentNode->getAttribute(AttributeKey::PARENT_NODE);
                continue;
            }

            $node->setAttribute(AttributeKey::SCOPE, $scope);
            return $scope;
        }

        return null;
    }
}
